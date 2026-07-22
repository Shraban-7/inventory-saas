<?php

namespace App\Application\Jobs;

use App\Domain\Entities\WebhookDeliveryStatus;
use App\Infrastructure\Models\Tenant;
use App\Infrastructure\Models\WebhookDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class DispatchPendingWebhookDeliveriesJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 60;

    public function __construct()
    {
        $this->onQueue('transactions');
    }

    public function handle(): void
    {
        Tenant::query()
            ->select('id')
            ->chunkById(100, function (Collection $tenants): void {
                foreach ($tenants as $tenant) {
                    app()->instance('current_tenant', $tenant);
                    $connection = $tenant->getConnection();
                    $usesMySqlSession = in_array(
                        $connection->getDriverName(),
                        ['mysql', 'mariadb'],
                        true,
                    );

                    try {
                        if ($usesMySqlSession) {
                            $connection->statement(
                                'SET @current_tenant_id = ?',
                                [$tenant->getKey()],
                            );
                        }

                        WebhookDelivery::query()
                            ->where('status', WebhookDeliveryStatus::Pending)
                            ->where(function ($query): void {
                                $query->whereNull('next_retry_at')
                                    ->orWhere('next_retry_at', '<=', now());
                            })
                            ->eachById(function (WebhookDelivery $delivery) use ($tenant): void {
                                DeliverWebhookJob::dispatch(
                                    (int) $tenant->getKey(),
                                    (string) $delivery->getKey(),
                                )->afterCommit();
                            }, 100);
                    } finally {
                        if ($usesMySqlSession) {
                            DB::statement('SET @current_tenant_id = NULL');
                        }

                        app()->forgetInstance('current_tenant');
                    }
                }
            });
    }
}
