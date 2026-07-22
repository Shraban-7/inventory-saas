<?php

namespace App\Application\Listeners;

use App\Application\Jobs\DeliverWebhookJob;
use App\Application\Services\CanonicalJson;
use App\Application\Services\WebhookEventPayloadMapper;
use App\Domain\Events\CreditNoteApproved;
use App\Domain\Events\GoodsReceived;
use App\Domain\Events\InvoiceCreated;
use App\Domain\Events\InvoiceVoided;
use App\Domain\Events\StockLow;
use App\Infrastructure\Models\Tenant;
use App\Infrastructure\Models\WebhookDelivery;
use App\Infrastructure\Models\WebhookEndpoint;
use Ramsey\Uuid\Uuid;

class CreateWebhookDeliveries
{
    private const UUID_NAMESPACE = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';

    public function __construct(
        private readonly WebhookEventPayloadMapper $mapper,
        private readonly CanonicalJson $canonicalJson,
    ) {}

    public function handle(
        InvoiceCreated|InvoiceVoided|GoodsReceived|CreditNoteApproved|StockLow $event,
    ): void {
        $tenantId = $this->resolveTenantId($event);

        if ($tenantId === null) {
            return;
        }

        $previousTenant = app()->bound('current_tenant')
            ? app()->make('current_tenant')
            : null;
        $tenant = Tenant::query()->find($tenantId);

        if (! $tenant instanceof Tenant) {
            return;
        }

        app()->instance('current_tenant', $tenant);

        try {
            $eventName = $this->mapper->eventName($event);
            $occurrenceId = Uuid::uuid5(
                self::UUID_NAMESPACE,
                "{$tenantId}:{$eventName->value}:{$this->mapper->subjectId($event)}",
            )->toString();
            $payload = $this->canonicalJson->encode(
                $this->mapper->map($event, $occurrenceId),
            );

            WebhookEndpoint::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->whereJsonContains('events', $eventName->value)
                ->eachById(function (WebhookEndpoint $endpoint) use (
                    $eventName,
                    $occurrenceId,
                    $payload,
                    $tenantId,
                ): void {
                    $delivery = WebhookDelivery::query()->firstOrCreate(
                        [
                            'tenant_id' => $tenantId,
                            'webhook_endpoint_id' => $endpoint->getKey(),
                            'occurrence_id' => $occurrenceId,
                        ],
                        [
                            'id' => Uuid::uuid5(
                                self::UUID_NAMESPACE,
                                "{$occurrenceId}:{$endpoint->getKey()}",
                            )->toString(),
                            'event' => $eventName,
                            'payload' => $payload,
                        ],
                    );

                    if ($delivery->wasRecentlyCreated) {
                        DeliverWebhookJob::dispatch(
                            $tenantId,
                            (string) $delivery->getKey(),
                        )->afterCommit();
                    }
                });
        } finally {
            if ($previousTenant instanceof Tenant) {
                app()->instance('current_tenant', $previousTenant);
            } else {
                app()->forgetInstance('current_tenant');
            }
        }
    }

    private function resolveTenantId(
        InvoiceCreated|InvoiceVoided|GoodsReceived|CreditNoteApproved|StockLow $event,
    ): ?int {
        if ($event instanceof StockLow) {
            return $event->tenantId;
        }

        if (! app()->bound('current_tenant')) {
            return null;
        }

        return current_tenant_id();
    }
}
