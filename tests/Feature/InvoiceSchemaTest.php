<?php

use App\Infrastructure\Models\Invoice;
use Illuminate\Database\QueryException;
use Tests\Support\SalesContext;

afterEach(fn () => app()->forgetInstance('current_tenant'));

function schemaInvoice(SalesContext $context, string $number): Invoice
{
    return Invoice::query()->create([
        'branch_id' => $context->branch->getKey(),
        'customer_id' => $context->customer->getKey(),
        'invoice_number' => $number,
        'invoice_date' => '2026-07-22',
        'total_amount' => '10.00',
        'tax_amount' => '0.00',
        'balance_due' => '10.00',
    ]);
}

it('requires invoice item unit and cost snapshots', function (string $missingColumn) {
    $context = SalesContext::create();
    $invoice = schemaInvoice($context, 'INV-2026-00001');
    $attributes = [
        'product_variant_id' => $context->variant->getKey(),
        'quantity' => '1.0000',
        'unit_price_at_sale' => '10.0000',
        'cost_price_at_sale' => '4.2500',
        'line_total' => '10.00',
    ];
    $attributes[$missingColumn] = null;

    expect(fn () => $invoice->items()->create($attributes))
        ->toThrow(QueryException::class);
})->with(['unit price' => ['unit_price_at_sale'], 'cost price' => ['cost_price_at_sale']]);

it('enforces invoice number uniqueness per tenant and permits the same number across tenants', function () {
    $first = SalesContext::create();
    schemaInvoice($first, 'INV-2026-00001');

    expect(fn () => schemaInvoice($first, 'INV-2026-00001'))
        ->toThrow(QueryException::class);

    $second = SalesContext::create();

    expect(schemaInvoice($second, 'INV-2026-00001'))->toBeInstanceOf(Invoice::class);
});
