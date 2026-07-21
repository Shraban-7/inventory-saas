<?php

namespace App\Application\Jobs;

use App\Infrastructure\Models\IdempotencyRequest;
use App\Infrastructure\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PruneIdempotencyRequestsJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Tenant::query()->select('id')->chunkById(100, function ($tenants): void {
            foreach ($tenants as $tenant) {
                app()->instance('current_tenant', $tenant);

                try {
                    IdempotencyRequest::query()
                        ->where('expires_at', '<', now())
                        ->delete();
                } finally {
                    app()->forgetInstance('current_tenant');
                }
            }
        });
    }
}
