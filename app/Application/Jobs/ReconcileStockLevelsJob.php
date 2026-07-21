<?php

namespace App\Application\Jobs;

use App\Domain\Entities\Quantity;
use App\Domain\Events\StockDriftDetected;
use App\Infrastructure\Models\AuditLog;
use App\Infrastructure\Models\StockLevel;
use App\Infrastructure\Models\StockMovement;
use App\Infrastructure\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ReconcileStockLevelsJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Tenant::query()->select('id')->chunkById(100, function ($tenants): void {
            foreach ($tenants as $tenant) {
                app()->instance('current_tenant', $tenant);

                try {
                    StockLevel::query()->chunkById(500, function ($levels): void {
                        foreach ($levels as $level) {
                            $movementTotal = (string) StockMovement::query()
                                ->where('product_variant_id', $level->product_variant_id)
                                ->where('branch_id', $level->branch_id)
                                ->sum('quantity_delta');
                            $actual = Quantity::from($movementTotal);
                            $cached = Quantity::from($level->quantity_on_hand);

                            if ($actual->equals($cached)) {
                                continue;
                            }

                            $difference = $actual->subtract($cached)->toDecimal();
                            AuditLog::query()->create([
                                'action' => 'STOCK_DRIFT_DETECTED',
                                'entity_type' => 'stock_level',
                                'entity_id' => $level->getKey(),
                                'old_values' => ['quantity_on_hand' => $cached->toDecimal()],
                                'new_values' => ['movement_total' => $actual->toDecimal(), 'difference' => $difference],
                            ]);
                            event(new StockDriftDetected($level->getKey(), $difference));
                        }
                    });
                } finally {
                    app()->forgetInstance('current_tenant');
                }
            }
        });
    }
}
