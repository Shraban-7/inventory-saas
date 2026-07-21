<?php

use App\Domain\Exceptions\InsufficientStockException;
use App\Domain\Exceptions\InvalidPurchasingDataException;
use App\Infrastructure\Models\Bill;
use App\Infrastructure\Models\BillPayment;
use App\Infrastructure\Models\GoodsReceiptNote;
use App\Infrastructure\Models\InventoryLot;
use App\Infrastructure\Models\Invoice;
use App\Infrastructure\Models\InvoiceItem;
use App\Infrastructure\Models\JournalEntry;
use App\Infrastructure\Models\PurchaseOrder;
use App\Infrastructure\Models\StockLevel;
use App\Infrastructure\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\PurchasingContext;
use Tests\Support\SalesContext;
use Tests\Support\SalesWorkers;

beforeEach(function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('MySQL or MariaDB is required for concurrency coverage.');
    }
});

afterEach(fn () => app()->forgetInstance('current_tenant'));

/** @param list<array<string, mixed>> $results */
function assertNoDatabaseRaceFailure(array $results): void
{
    foreach ($results as $result) {
        $message = mb_strtolower((string) ($result['message'] ?? ''));

        expect($message)
            ->not->toContain('deadlock')
            ->not->toContain('duplicate entry')
            ->not->toContain('lock wait timeout');
    }
}

it('replays one same-key goods receipt across concurrent workers', function () {
    $context = PurchasingContext::create();
    $variantId = (int) $context->sales->variant->getKey();
    StockLevel::query()->where('product_variant_id', $variantId)->update(['quantity_on_hand' => '0.0000']);
    InventoryLot::query()->where('product_variant_id', $variantId)->delete();

    $payload = [
        'tenant_id' => $context->sales->tenant->getKey(),
        'user_id' => $context->sales->user->getKey(),
        'branch_id' => $context->sales->branch->getKey(),
        'supplier_id' => $context->supplier->getKey(),
        'purchase_order_id' => null,
        'received_at' => '2026-07-22T10:00:00+00:00',
        'notes' => 'Concurrent dock receipt',
        'idempotency_key' => (string) Str::uuid(),
        'payload_hash' => hash('sha256', 'same-concurrent-grn-body'),
        'items' => [[
            'variant_id' => $variantId,
            'quantity' => '4.0000',
            'unit_cost' => '4.2500',
        ]],
    ];

    $results = SalesWorkers::run('process-grn', [$payload, $payload]);
    $grnIds = collect($results)->pluck('grn_id')->unique()->values()->all();

    assertNoDatabaseRaceFailure($results);
    expect(collect($results)->where('ok', true))->toHaveCount(2)
        ->and($grnIds)->toHaveCount(1)
        ->and(GoodsReceiptNote::query()->count())->toBe(1)
        ->and(StockMovement::query()
            ->where('source_type', 'grn')
            ->where('product_variant_id', $variantId)
            ->count())->toBe(1)
        ->and(InventoryLot::query()->where('product_variant_id', $variantId)->count())->toBe(1)
        ->and(JournalEntry::query()->where('reference_type', 'grn')->count())->toBe(1)
        ->and(StockLevel::query()->where('product_variant_id', $variantId)->value('quantity_on_hand'))->toBe('4.0000');
});

it('allows only one competing receipt against a purchase order balance', function () {
    $context = PurchasingContext::create();
    $variantId = (int) $context->sales->variant->getKey();
    StockLevel::query()->where('product_variant_id', $variantId)->update(['quantity_on_hand' => '0.0000']);
    InventoryLot::query()->where('product_variant_id', $variantId)->delete();
    $order = PurchaseOrder::query()->create([
        'tenant_id' => $context->sales->tenant->getKey(),
        'branch_id' => $context->sales->branch->getKey(),
        'supplier_id' => $context->supplier->getKey(),
        'po_number' => 'PO-RACE-'.Str::upper(Str::random(8)),
        'status' => 'confirmed',
        'order_date' => '2026-07-22',
    ]);
    $order->items()->create([
        'tenant_id' => $context->sales->tenant->getKey(),
        'product_variant_id' => $variantId,
        'quantity_ordered' => '5.0000',
        'quantity_received' => '0.0000',
        'unit_cost' => '4.2500',
    ]);
    $basePayload = [
        'tenant_id' => $context->sales->tenant->getKey(),
        'user_id' => $context->sales->user->getKey(),
        'branch_id' => $context->sales->branch->getKey(),
        'supplier_id' => $context->supplier->getKey(),
        'purchase_order_id' => $order->getKey(),
        'received_at' => '2026-07-22T10:00:00+00:00',
        'notes' => 'Competing PO receipt',
        'payload_hash' => hash('sha256', 'competing-po-receipt-body'),
        'items' => [[
            'variant_id' => $variantId,
            'quantity' => '4.0000',
            'unit_cost' => '4.2500',
        ]],
    ];
    $payloads = [
        [...$basePayload, 'idempotency_key' => (string) Str::uuid()],
        [...$basePayload, 'idempotency_key' => (string) Str::uuid()],
    ];

    $results = SalesWorkers::run('process-grn', $payloads);
    $failure = collect($results)->where('ok', false)->sole();
    $order->refresh()->load('items');

    assertNoDatabaseRaceFailure($results);
    expect(collect($results)->where('ok', true))->toHaveCount(1)
        ->and(collect($results)->where('ok', false))->toHaveCount(1)
        ->and($failure['error'])->toBe(InvalidPurchasingDataException::class)
        ->and($order->getRawOriginal('status'))->toBe('partially_received')
        ->and($order->items->sole()->quantity_received)->toBe('4.0000')
        ->and(GoodsReceiptNote::query()->count())->toBe(1)
        ->and(StockMovement::query()->where('source_type', 'grn')->where('product_variant_id', $variantId)->count())->toBe(1)
        ->and(InventoryLot::query()->where('product_variant_id', $variantId)->count())->toBe(1)
        ->and(JournalEntry::query()->where('reference_type', 'grn')->count())->toBe(1)
        ->and(StockLevel::query()->where('product_variant_id', $variantId)->value('quantity_on_hand'))->toBe('4.0000');
});

it('allows only one competing FIFO sale against shared lots', function () {
    $context = SalesContext::create();
    $variantId = (int) $context->variant->getKey();
    StockLevel::query()->where('product_variant_id', $variantId)->update(['quantity_on_hand' => '10.0000']);
    InventoryLot::query()->where('product_variant_id', $variantId)->delete();
    InventoryLot::query()->create([
        'product_variant_id' => $variantId,
        'branch_id' => $context->branch->getKey(),
        'quantity_remaining' => '10.0000',
        'unit_cost' => '4.2500',
        'received_at' => '2026-07-01 00:00:00',
    ]);
    $payload = [
        'tenant_id' => $context->tenant->getKey(),
        'user_id' => $context->user->getKey(),
        'branch_id' => $context->branch->getKey(),
        'customer_id' => $context->customer->getKey(),
        'invoice_date' => '2026-07-22',
        'items' => [[
            'variant_id' => $variantId,
            'quantity' => '6.0000',
            'unit_price' => '10.0000',
            'tax_id' => $context->tax->getKey(),
        ]],
    ];

    $results = SalesWorkers::run('create-invoice', [$payload, $payload]);
    $failure = collect($results)->where('ok', false)->sole();
    $invoice = Invoice::query()->with('items')->sole();
    $journal = JournalEntry::query()->where('reference_type', 'invoice')->with('lines.account')->sole();
    $journalByAccount = $journal->lines->keyBy(fn ($line) => $line->account->code);

    assertNoDatabaseRaceFailure($results);
    expect(collect($results)->where('ok', true))->toHaveCount(1)
        ->and(collect($results)->where('ok', false))->toHaveCount(1)
        ->and($failure['error'])->toBe(InsufficientStockException::class)
        ->and(StockLevel::query()->where('product_variant_id', $variantId)->value('quantity_on_hand'))->toBe('4.0000')
        ->and(InventoryLot::query()->where('product_variant_id', $variantId)->sole()->quantity_remaining)->toBe('4.0000')
        ->and(Invoice::query()->count())->toBe(1)
        ->and(InvoiceItem::query()->where('invoice_id', $invoice->getKey())->sole()->cost_total_at_sale)->toBe('25.50')
        ->and(StockMovement::query()->where('source_type', 'invoice')->where('product_variant_id', $variantId)->count())->toBe(1)
        ->and(JournalEntry::query()->where('reference_type', 'invoice')->count())->toBe(1)
        ->and($journalByAccount->get('5000')->debit)->toBe('25.50')
        ->and($journalByAccount->get('1300')->credit)->toBe('25.50');
});

it('allows only one concurrent payment that fits the bill balance', function () {
    $context = PurchasingContext::create();
    $bill = Bill::query()->create([
        'tenant_id' => $context->sales->tenant->getKey(),
        'branch_id' => $context->sales->branch->getKey(),
        'supplier_id' => $context->supplier->getKey(),
        'bill_number' => 'BILL-RACE-'.Str::upper(Str::random(8)),
        'bill_date' => '2026-07-22',
        'status' => 'approved',
        'total_amount' => '10.00',
        'tax_amount' => '0.00',
        'balance_due' => '10.00',
    ]);
    $payload = [
        'tenant_id' => $context->sales->tenant->getKey(),
        'user_id' => $context->sales->user->getKey(),
        'bill_id' => $bill->getKey(),
        'amount' => '7.00',
        'payment_method' => 'cash',
        'payment_date' => '2026-07-23',
        'reference' => 'Concurrent payment',
    ];

    $results = SalesWorkers::run('pay-bill', [$payload, $payload]);
    $failure = collect($results)->where('ok', false)->sole();
    $bill->refresh();

    assertNoDatabaseRaceFailure($results);
    expect(collect($results)->where('ok', true))->toHaveCount(1)
        ->and(collect($results)->where('ok', false))->toHaveCount(1)
        ->and($failure['error'])->toBe(InvalidPurchasingDataException::class)
        ->and(BillPayment::query()->where('bill_id', $bill->getKey())->count())->toBe(1)
        ->and(JournalEntry::query()->where('reference_type', 'bill_payment')->count())->toBe(1)
        ->and($bill->balance_due)->toBe('3.00')
        ->and($bill->getRawOriginal('status'))->toBe('partially_paid');
});
