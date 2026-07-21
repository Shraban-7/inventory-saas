<?php

use App\Domain\Services\InvoiceNumberService;
use App\Infrastructure\Models\Invoice;
use App\Infrastructure\Models\StockLevel;
use Illuminate\Support\Facades\DB;
use Tests\Support\SalesContext;
use Tests\Support\SalesWorkers;

beforeEach(function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('MySQL or MariaDB is required for concurrency coverage.');
    }
});

afterEach(fn () => app()->forgetInstance('current_tenant'));

it('creates exactly one hundred invoices with unique sequential numbers through named locks', function () {
    $context = SalesContext::create();
    StockLevel::query()
        ->where('product_variant_id', $context->variant->getKey())
        ->update(['quantity_on_hand' => '1000.0000']);
    $payloads = array_fill(0, 10, [
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
        'count' => 10,
    ]);

    $results = SalesWorkers::run('create-invoice', $payloads);
    $numbers = collect($results)->flatMap(fn (array $result): array => $result['invoice_numbers'] ?? [])->sort()->values()->all();
    $expected = array_map(
        static fn (int $sequence): string => sprintf('INV-2026-%05d', $sequence),
        range(1, 100),
    );

    expect(collect($results)->where('ok', true))->toHaveCount(10)
        ->and($numbers)->toHaveCount(100)
        ->and(array_values(array_unique($numbers)))->toBe($expected)
        ->and(Invoice::query()->count())->toBe(100);

    $otherTenant = SalesContext::create();
    app()->instance('current_tenant', $otherTenant->tenant);

    expect(app(InvoiceNumberService::class)->next(2026))->toBe('INV-2026-00001');
});
