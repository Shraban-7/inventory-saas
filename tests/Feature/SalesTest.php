<?php

use App\Application\Actions\Accounting\CreateJournalEntryAction;
use App\Application\DTOs\JournalEntryData;
use App\Domain\Exceptions\UnbalancedJournalEntryException;
use App\Infrastructure\Models\AuditLog;
use App\Infrastructure\Models\ChartOfAccount;
use App\Infrastructure\Models\Invoice;
use App\Infrastructure\Models\InvoiceItem;
use App\Infrastructure\Models\JournalEntry;
use App\Infrastructure\Models\JournalEntry as JournalEntryModel;
use App\Infrastructure\Models\JournalEntryLine;
use App\Infrastructure\Models\StockLevel;
use App\Infrastructure\Models\StockMovement;
use Illuminate\Support\Str;
use Tests\Support\SalesContext;

afterEach(fn () => app()->forgetInstance('current_tenant'));

it('creates one atomic idempotent sale with stock journal and audit records', function () {
    $context = SalesContext::create();
    $key = (string) Str::uuid();
    $payload = $context->invoicePayload();
    $this->actingAs($context->user);

    $first = $this->postJson('/api/v1/invoices', $payload, ['Idempotency-Key' => $key])
        ->assertCreated()
        ->assertJsonPath('data.total_amount', '21.50')
        ->assertJsonPath('data.tax_amount', '1.50');
    $invoiceId = $first->json('data.id');

    $this->postJson('/api/v1/invoices', $payload, ['Idempotency-Key' => $key])
        ->assertCreated()
        ->assertJsonPath('data.id', $invoiceId);

    expect(Invoice::query()->count())->toBe(1)
        ->and(InvoiceItem::query()->count())->toBe(1)
        ->and(StockMovement::query()->where('type', 'SALES_DEDUCTION')->count())->toBe(1)
        ->and(StockLevel::query()->where('product_variant_id', $context->variant->getKey())->value('quantity_on_hand'))->toBe('18.0000')
        ->and(AuditLog::query()->where('action', 'INVOICE_CREATED')->count())->toBe(1);

    $journal = JournalEntry::query()->where('reference_type', 'invoice')->where('reference_id', $invoiceId)->firstOrFail();
    expect((float) $journal->lines()->sum('debit'))->toBe((float) $journal->lines()->sum('credit'));

    $this->postJson('/api/v1/invoices', [...$payload, 'notes' => 'Changed'], ['Idempotency-Key' => $key])
        ->assertConflict()
        ->assertHeader('Content-Type', 'application/problem+json');
    expect(Invoice::query()->count())->toBe(1);
});

it('fully rolls back an invoice when stock is insufficient', function () {
    $context = SalesContext::create();
    StockLevel::query()
        ->where('product_variant_id', $context->variant->getKey())
        ->update(['quantity_on_hand' => '1.0000']);

    $this->actingAs($context->user)
        ->postJson('/api/v1/invoices', $context->invoicePayload(), ['Idempotency-Key' => (string) Str::uuid()])
        ->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/problem+json');

    expect(Invoice::query()->count())->toBe(0)
        ->and(InvoiceItem::query()->count())->toBe(0)
        ->and(StockMovement::query()->where('type', 'SALES_DEDUCTION')->count())->toBe(0)
        ->and(JournalEntry::query()->count())->toBe(0)
        ->and(JournalEntryLine::query()->count())->toBe(0)
        ->and(StockLevel::query()->where('product_variant_id', $context->variant->getKey())->value('quantity_on_hand'))->toBe('1.0000');
});

it('fully rolls back stock and invoice writes when a required journal account is missing', function () {
    $context = SalesContext::create();
    ChartOfAccount::query()->where('code', '4000')->delete();

    $this->actingAs($context->user)
        ->postJson('/api/v1/invoices', $context->invoicePayload(), ['Idempotency-Key' => (string) Str::uuid()])
        ->assertUnprocessable();

    expect(Invoice::query()->count())->toBe(0)
        ->and(InvoiceItem::query()->count())->toBe(0)
        ->and(StockMovement::query()->count())->toBe(0)
        ->and(JournalEntry::query()->count())->toBe(0)
        ->and(StockLevel::query()->where('product_variant_id', $context->variant->getKey())->value('quantity_on_hand'))->toBe('20.0000');
});

it('fully rolls back an invoice when journal balancing fails', function () {
    $context = SalesContext::create();
    app()->instance(CreateJournalEntryAction::class, new class extends CreateJournalEntryAction
    {
        public function handle(JournalEntryData $data): JournalEntryModel
        {
            throw new UnbalancedJournalEntryException;
        }
    });

    $this->actingAs($context->user)
        ->postJson('/api/v1/invoices', $context->invoicePayload(), ['Idempotency-Key' => (string) Str::uuid()])
        ->assertUnprocessable();

    expect(Invoice::query()->count())->toBe(0)
        ->and(InvoiceItem::query()->count())->toBe(0)
        ->and(StockMovement::query()->count())->toBe(0)
        ->and(JournalEntry::query()->count())->toBe(0)
        ->and(StockLevel::query()->where('product_variant_id', $context->variant->getKey())->value('quantity_on_hand'))->toBe('20.0000');
});

it('creates multi-line sales atomically and preserves price cost and tax snapshots', function () {
    $context = SalesContext::create();
    $items = [
        [
            'variant_id' => $context->variant->getKey(),
            'quantity' => '1.2500',
            'unit_price' => '10.0050',
            'tax_id' => $context->tax->getKey(),
        ],
        [
            'variant_id' => $context->secondVariant->getKey(),
            'quantity' => '2.0000',
            'unit_price' => '6.0000',
        ],
    ];

    $response = $this->actingAs($context->user)
        ->postJson('/api/v1/invoices', $context->invoicePayload($items), ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated();
    $invoice = Invoice::query()->with('items')->findOrFail($response->json('data.id'));
    $originalTotal = $invoice->total_amount;
    $firstItem = $invoice->items->firstWhere('product_variant_id', $context->variant->getKey());

    $context->variant->update(['sale_price' => '99.0000', 'cost_price' => '88.0000']);
    $context->tax->update(['rate' => '20.0000']);
    $invoice->refresh()->load('items');
    $firstItem = $invoice->items->firstWhere('product_variant_id', $context->variant->getKey());

    expect($invoice->items)->toHaveCount(2)
        ->and($invoice->total_amount)->toBe($originalTotal)
        ->and($firstItem?->unit_price_at_sale)->toBe('10.0050')
        ->and($firstItem?->cost_price_at_sale)->toBe('4.2500')
        ->and($firstItem?->tax_rate_at_sale)->toBe('7.5000')
        ->and(StockMovement::query()->where('type', 'SALES_DEDUCTION')->count())->toBe(2);
});
