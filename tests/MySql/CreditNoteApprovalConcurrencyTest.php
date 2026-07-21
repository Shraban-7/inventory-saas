<?php

use App\Infrastructure\Models\CreditNote;
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

it('approves one draft exactly once under concurrent workers', function () {
    $context = SalesContext::create();
    StockLevel::query()
        ->where('product_variant_id', $context->variant->getKey())
        ->update(['quantity_on_hand' => '0.0000']);
    $credit = CreditNote::query()->create([
        'branch_id' => $context->branch->getKey(),
        'customer_id' => $context->customer->getKey(),
        'reason' => 'Concurrent return',
        'total_amount' => '10.75',
    ]);
    $credit->items()->create([
        'product_variant_id' => $context->variant->getKey(),
        'tax_id' => $context->tax->getKey(),
        'quantity' => '1.0000',
        'unit_price' => '10.0000',
        'cost_price_at_return' => '4.2500',
        'tax_rate_at_return' => '7.5000',
        'line_total' => '10.75',
    ]);
    $payload = [
        'tenant_id' => $context->tenant->getKey(),
        'user_id' => $context->user->getKey(),
        'credit_note_id' => $credit->getKey(),
    ];

    $results = SalesWorkers::run('approve-credit', [$payload, $payload]);

    expect(collect($results)->where('ok', true))->toHaveCount(1)
        ->and(collect($results)->where('ok', false))->toHaveCount(1)
        ->and(CreditNote::query()->findOrFail($credit->getKey())->getRawOriginal('status'))->toBe('approved')
        ->and(StockLevel::query()->where('product_variant_id', $context->variant->getKey())->value('quantity_on_hand'))->toBe('1.0000')
        ->and(StockMovement::query()->where('type', 'SALES_RETURN')->where('source_type', 'credit_note')->count())->toBe(1)
        ->and(JournalEntry::query()->where('reference_type', 'credit_note')->where('reference_id', $credit->getKey())->count())->toBe(1);
});
