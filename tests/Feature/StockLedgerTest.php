<?php

use App\Application\Jobs\ReconcileStockLevelsJob;
use App\Domain\Exceptions\ImmutableRecordException;
use App\Infrastructure\Models\AuditLog;
use App\Infrastructure\Models\StockLevel;
use App\Infrastructure\Models\StockMovement;
use Illuminate\Support\Facades\Schema;

it('keeps stock movements append only without an updated timestamp', function () {
    $context = inventorySetup();
    $movement = StockMovement::query()->create([
        'product_variant_id' => $context['variant']->getKey(),
        'branch_id' => $context['from']->getKey(),
        'type' => 'OPENING_BALANCE',
        'quantity_delta' => '1.0000',
    ]);

    expect(Schema::hasColumn('stock_movements', 'updated_at'))->toBeFalse()
        ->and(fn () => $movement->update(['quantity_delta' => '2.0000']))
        ->toThrow(ImmutableRecordException::class);
});

it('records stock drift during reconciliation', function () {
    $context = inventorySetup();
    StockLevel::query()->create([
        'product_variant_id' => $context['variant']->getKey(),
        'branch_id' => $context['from']->getKey(),
        'quantity_on_hand' => '5.0000',
    ]);
    StockMovement::query()->create([
        'product_variant_id' => $context['variant']->getKey(),
        'branch_id' => $context['from']->getKey(),
        'type' => 'OPENING_BALANCE',
        'quantity_delta' => '3.0000',
    ]);

    app()->forgetInstance('current_tenant');
    (new ReconcileStockLevelsJob)->handle();
    app()->instance('current_tenant', $context['tenant']);

    expect(AuditLog::query()->where('action', 'STOCK_DRIFT_DETECTED')->count())->toBe(1);
});
