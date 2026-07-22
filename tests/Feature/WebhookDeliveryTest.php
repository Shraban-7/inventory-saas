<?php

use App\Application\Contracts\DnsResolver;
use App\Application\Jobs\DeliverWebhookJob;
use App\Application\Jobs\DispatchPendingWebhookDeliveriesJob;
use App\Application\Listeners\CreateWebhookDeliveries;
use App\Application\Services\WebhookDestinationValidator;
use App\Application\Services\WebhookEventPayloadMapper;
use App\Application\Services\WebhookSigner;
use App\Domain\Entities\WebhookDeliveryStatus;
use App\Domain\Entities\WebhookEvent;
use App\Domain\Events\CreditNoteApproved;
use App\Domain\Events\GoodsReceived;
use App\Domain\Events\InvoiceCreated;
use App\Domain\Events\InvoiceVoided;
use App\Domain\Events\StockLow;
use App\Infrastructure\Models\Invoice;
use App\Infrastructure\Models\WebhookDelivery;
use App\Infrastructure\Models\WebhookEndpoint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\SalesContext;

afterEach(function (): void {
    app()->forgetInstance('current_tenant');
    Carbon::setTestNow();
});

beforeEach(function (): void {
    app()->instance(DnsResolver::class, new class implements DnsResolver
    {
        public function resolve(string $host): array
        {
            return ['93.184.216.34'];
        }
    });
});

function webhookDeliveryFixture(SalesContext $context, array $events = ['invoice.created']): array
{
    app()->instance('current_tenant', $context->tenant);
    $endpoint = WebhookEndpoint::query()->create([
        'url' => 'https://example.com/webhook',
        'secret' => 'top-secret',
        'events' => $events,
    ]);
    $delivery = WebhookDelivery::query()->create([
        'id' => (string) Str::uuid(),
        'occurrence_id' => (string) Str::uuid(),
        'webhook_endpoint_id' => $endpoint->getKey(),
        'event' => WebhookEvent::InvoiceCreated,
        'payload' => '{"data":{"id":1},"event":"invoice.created","id":"occurrence"}',
    ]);

    return [$endpoint, $delivery];
}

it('maps each supported domain event explicitly', function () {
    $mapper = app(WebhookEventPayloadMapper::class);

    expect($mapper->eventName(new InvoiceCreated(1)))->toBe(WebhookEvent::InvoiceCreated)
        ->and($mapper->eventName(new InvoiceVoided(1)))->toBe(WebhookEvent::InvoiceVoided)
        ->and($mapper->eventName(new GoodsReceived(1)))->toBe(WebhookEvent::GoodsReceived)
        ->and($mapper->eventName(new CreditNoteApproved(1)))->toBe(WebhookEvent::CreditNoteApproved)
        ->and($mapper->eventName(new StockLow(1, 2, 3, '4.0000', 5, 6)))->toBe(WebhookEvent::StockLow);
});

it('verifies wire signatures and rejects tampered header values', function () {
    $signer = app(WebhookSigner::class);
    $payload = '{"amount":"10.00"}';
    $wire = 'sha256='.$signer->sign($payload, 'secret');

    expect($signer->verify($payload, $wire, 'secret'))->toBeTrue()
        ->and($signer->verify($payload, $signer->sign($payload, 'secret'), 'secret'))->toBeTrue()
        ->and($signer->verify($payload, 'sha256=deadbeef', 'secret'))->toBeFalse()
        ->and($signer->verify('{"amount":"11.00"}', $wire, 'secret'))->toBeFalse();
});

it('delivers exact signed bytes with a stable delivery identifier', function () {
    $context = SalesContext::create();
    [, $delivery] = webhookDeliveryFixture($context);
    Http::preventStrayRequests();
    Http::fake(['example.com/*' => Http::response('', 204)]);

    (new DeliverWebhookJob(
        (int) $context->tenant->getKey(),
        (string) $delivery->getKey(),
    ))->handle(
        app(WebhookSigner::class),
        app(WebhookDestinationValidator::class),
    );

    $signer = app(WebhookSigner::class);
    Http::assertSent(fn ($request): bool => $request->body() === $delivery->payload
        && $request->header('X-Inventory-Delivery')[0] === $delivery->getKey()
        && $request->header('X-Inventory-Signature')[0]
            === 'sha256='.$signer->sign($delivery->payload, 'top-secret'));
    expect($delivery->fresh())
        ->status->toBe(WebhookDeliveryStatus::Delivered)
        ->attempts->toBe(1)
        ->delivered_at->not->toBeNull()
        ->next_retry_at->toBeNull();
});

it('revalidates DNS at delivery time and blocks unsafe rebinding', function (string $address) {
    $context = SalesContext::create();
    [, $delivery] = webhookDeliveryFixture($context);
    app()->instance(DnsResolver::class, new class($address) implements DnsResolver
    {
        public function __construct(private readonly string $address) {}

        public function resolve(string $host): array
        {
            return [$this->address];
        }
    });
    Http::preventStrayRequests();
    Http::fake();

    (new DeliverWebhookJob(
        (int) $context->tenant->getKey(),
        (string) $delivery->getKey(),
    ))->handle(
        app(WebhookSigner::class),
        app(WebhookDestinationValidator::class),
    );

    Http::assertNothingSent();
    expect($delivery->fresh())
        ->status->toBe(WebhookDeliveryStatus::Failed)
        ->attempts->toBe(1)
        ->response_code->toBeNull()
        ->error_detail->toBe('The webhook destination is not a public address.');
})->with([
    'loopback' => ['127.0.0.1'],
    'private' => ['10.0.0.1'],
    'link local' => ['169.254.1.1'],
    'multicast' => ['224.0.0.1'],
    'reserved' => ['192.0.2.1'],
    'metadata service' => ['168.63.129.16'],
]);

it('retries with durable exponential backoff up to five attempts', function () {
    Carbon::setTestNow('2026-07-22 10:00:00');
    $context = SalesContext::create();
    [, $transient] = webhookDeliveryFixture($context);
    Http::preventStrayRequests();
    Http::fake(fn () => Http::response('', 500));
    $job = new DeliverWebhookJob(
        (int) $context->tenant->getKey(),
        (string) $transient->getKey(),
    );
    $delays = [60, 300, 900, 3600];

    foreach ($delays as $index => $delay) {
        $job->handle(
            app(WebhookSigner::class),
            app(WebhookDestinationValidator::class),
        );

        $fresh = $transient->fresh();
        expect($fresh)
            ->status->toBe(WebhookDeliveryStatus::Pending)
            ->attempts->toBe($index + 1)
            ->and($fresh->next_retry_at?->equalTo(now()->addSeconds($delay)))->toBeTrue();

        Carbon::setTestNow(now()->addSeconds($delay));
    }

    $job->handle(
        app(WebhookSigner::class),
        app(WebhookDestinationValidator::class),
    );

    expect($transient->fresh())
        ->status->toBe(WebhookDeliveryStatus::Failed)
        ->attempts->toBe(5)
        ->next_retry_at->toBeNull();
});

it('fails terminal client errors without scheduling another retry', function () {
    $context = SalesContext::create();
    [, $terminal] = webhookDeliveryFixture($context);
    Http::preventStrayRequests();
    Http::fake(fn () => Http::response('', 422));

    (new DeliverWebhookJob(
        (int) $context->tenant->getKey(),
        (string) $terminal->getKey(),
    ))->handle(
        app(WebhookSigner::class),
        app(WebhookDestinationValidator::class),
    );

    expect($terminal->fresh())
        ->status->toBe(WebhookDeliveryStatus::Failed)
        ->attempts->toBe(1)
        ->response_code->toBe(422)
        ->next_retry_at->toBeNull();
});

it('claims deliveries atomically so duplicates neither POST nor steal a live lease', function () {
    Carbon::setTestNow('2026-07-22 11:00:00');
    $context = SalesContext::create();
    [, $delivery] = webhookDeliveryFixture($context);
    Http::preventStrayRequests();
    Http::fake(['example.com/*' => Http::response('', 204)]);

    $job = new DeliverWebhookJob(
        (int) $context->tenant->getKey(),
        (string) $delivery->getKey(),
    );
    $job->handle(
        app(WebhookSigner::class),
        app(WebhookDestinationValidator::class),
    );
    Http::assertSentCount(1);
    expect($delivery->fresh()->status)->toBe(WebhookDeliveryStatus::Delivered);

    Http::fake();
    $job->handle(
        app(WebhookSigner::class),
        app(WebhookDestinationValidator::class),
    );
    Http::assertNothingSent();

    [, $leased] = webhookDeliveryFixture($context);
    $leased->forceFill([
        'attempts' => 1,
        'next_retry_at' => now()->addSeconds(DeliverWebhookJob::LEASE_SECONDS),
    ])->save();
    Http::fake(['example.com/*' => Http::response('', 204)]);
    (new DeliverWebhookJob(
        (int) $context->tenant->getKey(),
        (string) $leased->getKey(),
    ))->handle(
        app(WebhookSigner::class),
        app(WebhookDestinationValidator::class),
    );
    Http::assertNothingSent();
    expect($leased->fresh())
        ->status->toBe(WebhookDeliveryStatus::Pending)
        ->attempts->toBe(1);

    Carbon::setTestNow(now()->addSeconds(DeliverWebhookJob::LEASE_SECONDS + 1));
    Queue::fake();
    (new DispatchPendingWebhookDeliveriesJob)->handle();
    Queue::assertPushed(
        DeliverWebhookJob::class,
        fn (DeliverWebhookJob $queued): bool => $queued->deliveryId === $leased->getKey(),
    );
});

it('creates one stable delivery per subscribed endpoint', function () {
    $context = SalesContext::create();
    app()->instance('current_tenant', $context->tenant);
    WebhookEndpoint::query()->create([
        'url' => 'https://example.com/webhook',
        'secret' => 'secret',
        'events' => ['invoice.created'],
    ]);
    $invoice = Invoice::query()->create([
        'branch_id' => $context->branch->getKey(),
        'customer_id' => $context->customer->getKey(),
        'invoice_number' => 'INV-WEBHOOK-1',
        'invoice_date' => '2026-07-22',
        'total_amount' => '10.00',
        'tax_amount' => '0.00',
        'balance_due' => '10.00',
    ]);
    Queue::fake();
    $listener = app(CreateWebhookDeliveries::class);
    $event = new InvoiceCreated((int) $invoice->getKey());

    $listener->handle($event);
    $first = WebhookDelivery::query()->firstOrFail();
    $listener->handle($event);

    expect(WebhookDelivery::query()->count())->toBe(1)
        ->and(WebhookDelivery::query()->firstOrFail()->getKey())->toBe($first->getKey())
        ->and($first->payload)->toContain('"event":"invoice.created"');
});

it('fans out unbound StockLow only to the event tenant and fail-closes unbound sales events', function () {
    $tenantA = SalesContext::create();
    WebhookEndpoint::query()->create([
        'url' => 'https://example.com/a',
        'secret' => 'secret-a',
        'events' => ['stock.low', 'invoice.created'],
    ]);
    $tenantB = SalesContext::create();
    WebhookEndpoint::query()->create([
        'url' => 'https://example.com/b',
        'secret' => 'secret-b',
        'events' => ['stock.low', 'invoice.created'],
    ]);
    app()->forgetInstance('current_tenant');
    Queue::fake();

    app(CreateWebhookDeliveries::class)->handle(new StockLow(
        (int) $tenantA->tenant->getKey(),
        (int) $tenantA->variant->getKey(),
        (int) $tenantA->branch->getKey(),
        '1.0000',
        5,
        99,
    ));

    expect(WebhookDelivery::withoutGlobalScopes()->count())->toBe(1);
    $delivery = WebhookDelivery::withoutGlobalScopes()->firstOrFail();
    expect($delivery->tenant_id)->toBe($tenantA->tenant->getKey())
        ->and($delivery->payload)->toContain('"event":"stock.low"');

    app(CreateWebhookDeliveries::class)->handle(new InvoiceCreated(123));
    expect(WebhookDelivery::withoutGlobalScopes()->count())->toBe(1);
});

it('sweeps only due pending deliveries and delivered jobs are no-ops', function () {
    $context = SalesContext::create();
    [, $due] = webhookDeliveryFixture($context);
    $due->forceFill(['next_retry_at' => now()->subMinute()])->save();
    [, $future] = webhookDeliveryFixture($context);
    $future->forceFill(['next_retry_at' => now()->addHour()])->save();
    Queue::fake();

    (new DispatchPendingWebhookDeliveriesJob)->handle();

    Queue::assertPushed(
        DeliverWebhookJob::class,
        fn (DeliverWebhookJob $job): bool => $job->deliveryId === $due->getKey(),
    );
    Queue::assertNotPushed(
        DeliverWebhookJob::class,
        fn (DeliverWebhookJob $job): bool => $job->deliveryId === $future->getKey(),
    );

    $due->forceFill(['status' => WebhookDeliveryStatus::Delivered])->save();
    Http::fake();
    (new DeliverWebhookJob(
        (int) $context->tenant->getKey(),
        (string) $due->getKey(),
    ))->handle(
        app(WebhookSigner::class),
        app(WebhookDestinationValidator::class),
    );
    Http::assertNothingSent();
});
