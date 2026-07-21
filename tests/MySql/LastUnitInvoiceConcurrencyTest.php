<?php

use App\Infrastructure\Models\Invoice;
use App\Infrastructure\Models\JournalEntry;
use App\Infrastructure\Models\StockLevel;
use App\Infrastructure\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Tests\Support\SalesContext;
use Tests\Support\SalesWorkers;

beforeEach(function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('MySQL or MariaDB is required for concurrency coverage.');
    }
});

afterEach(fn () => app()->forgetInstance('current_tenant'));

it('allows exactly one concurrent sale of the last stock unit', function () {
    $context = SalesContext::create();
    StockLevel::query()
        ->where('product_variant_id', $context->variant->getKey())
        ->update(['quantity_on_hand' => '1.0000']);
    $payload = [
        'tenant_id' => $context->tenant->getKey(),
        'user_id' => $context->user->getKey(),
        'branch_id' => $context->branch->getKey(),
        'customer_id' => $context->customer->getKey(),
        'invoice_date' => '2026-07-22',
        'items' => [[
            'variant_id' => $context->variant->getKey(),
            'quantity' => '1.0000',
            'unit_price' => '10.0000',
            'tax_id' => $context->tax->getKey(),
        ]],
    ];

    $results = SalesWorkers::run('create-invoice', [$payload, $payload]);

    expect(collect($results)->where('ok', true))->toHaveCount(1)
        ->and(collect($results)->where('ok', false))->toHaveCount(1)
        ->and(StockLevel::query()->where('product_variant_id', $context->variant->getKey())->value('quantity_on_hand'))->toBe('0.0000')
        ->and(Invoice::query()->count())->toBe(1)
        ->and(StockMovement::query()->where('type', 'SALES_DEDUCTION')->count())->toBe(1)
        ->and(JournalEntry::query()->where('reference_type', 'invoice')->count())->toBe(1);
});
