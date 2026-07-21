<?php

use App\Application\Actions\Purchasing\ProcessGoodsReceiptAction;
use App\Application\Actions\Purchasing\ProcessSupplierReturnAction;
use App\Application\DTOs\GoodsReceiptData;
use App\Application\DTOs\GrnItemData;
use App\Application\DTOs\SupplierReturnData;
use App\Application\DTOs\SupplierReturnItemData;
use App\Domain\Exceptions\IdempotencyConflictException;
use App\Domain\Exceptions\InsufficientStockException;
use App\Infrastructure\Models\Bill;
use App\Infrastructure\Models\BillPayment;
use App\Infrastructure\Models\GoodsReceiptNote;
use App\Infrastructure\Models\InventoryLot;
use App\Infrastructure\Models\InvoiceItem;
use App\Infrastructure\Models\JournalEntry;
use App\Infrastructure\Models\PurchaseOrder;
use App\Infrastructure\Models\StockLevel;
use App\Infrastructure\Models\StockMovement;
use App\Infrastructure\Models\Supplier;
use App\Infrastructure\Models\SupplierReturn;
use Illuminate\Support\Str;
use Tests\Support\PurchasingContext;

afterEach(fn () => app()->forgetInstance('current_tenant'));

function purchasingKey(): string
{
    return (string) Str::uuid();
}

/** @return array<string, array{debit: string, credit: string}> */
function purchasingJournalLines(string $type, int $id): array
{
    $entry = JournalEntry::query()
        ->where('reference_type', $type)
        ->where('reference_id', $id)
        ->with('lines.account')
        ->firstOrFail();

    return $entry->lines->mapWithKeys(fn ($line) => [
        $line->account->code => ['debit' => $line->debit, 'credit' => $line->credit],
    ])->all();
}

function createConfirmedOrder(PurchasingContext $context): int
{
    $id = (int) test()->actingAs($context->sales->user)
        ->postJson('/api/v1/purchase-orders', $context->purchaseOrderPayload(), ['Idempotency-Key' => purchasingKey()])
        ->assertCreated()
        ->json('data.id');

    test()->putJson("/api/v1/purchase-orders/{$id}/confirm", [], ['Idempotency-Key' => purchasingKey()])
        ->assertSuccessful();

    return $id;
}

it('provides tenant isolated supplier CRUD without exposing ciphertext', function () {
    $tenantA = PurchasingContext::create();
    $tenantB = PurchasingContext::create();
    $this->actingAs($tenantA->sales->user);

    $created = $this->postJson('/api/v1/suppliers', [
        'name' => 'Secure Metals',
        'contact_name' => 'Grace Hopper',
        'email' => 'grace@example.test',
        'phone' => '555-0111',
        'address' => ['city' => 'Chattogram'],
    ])->assertCreated()
        ->assertJsonPath('data.email', 'grace@example.test');
    $supplierId = (int) $created->json('data.id');

    $raw = Supplier::query()->findOrFail($supplierId)->getRawOriginal('email');
    expect($raw)->not->toBe('grace@example.test');

    $this->getJson('/api/v1/suppliers')
        ->assertSuccessful()
        ->assertJsonFragment(['id' => $supplierId])
        ->assertJsonMissing(['id' => $tenantB->supplier->getKey()])
        ->assertDontSee((string) $raw, false);
    $this->getJson("/api/v1/suppliers/{$supplierId}")
        ->assertSuccessful()
        ->assertJsonPath('data.contact_name', 'Grace Hopper');
    $this->putJson("/api/v1/suppliers/{$supplierId}", [
        'name' => 'Secure Metals Ltd',
        'email' => 'billing@example.test',
    ])->assertSuccessful()
        ->assertJsonPath('data.name', 'Secure Metals Ltd');
    $this->getJson("/api/v1/suppliers/{$tenantB->supplier->getKey()}")->assertNotFound();
});

it('creates filters confirms and cancels purchase orders with state conflicts', function () {
    $context = PurchasingContext::create();
    $this->actingAs($context->sales->user);

    $response = $this->postJson('/api/v1/purchase-orders', $context->purchaseOrderPayload(), [
        'Idempotency-Key' => purchasingKey(),
    ])->assertCreated()
        ->assertJsonPath('data.po_number', 'PO-2026-00001')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.items.0.quantity_ordered', '5.0000')
        ->assertJsonPath('data.items.0.unit_cost', '4.2500');
    $id = (int) $response->json('data.id');

    $this->getJson('/api/v1/purchase-orders?status=draft&supplier_id='.$context->supplier->getKey())
        ->assertSuccessful()
        ->assertJsonFragment(['id' => $id]);
    $this->getJson('/api/v1/purchase-orders?supplier_id='.$context->secondSupplier->getKey())
        ->assertSuccessful()
        ->assertJsonMissing(['id' => $id]);

    $this->putJson("/api/v1/purchase-orders/{$id}/confirm", [], ['Idempotency-Key' => purchasingKey()])
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'confirmed');
    $this->putJson("/api/v1/purchase-orders/{$id}/cancel", [], ['Idempotency-Key' => purchasingKey()])
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'cancelled');

    $received = PurchaseOrder::query()->create([
        'tenant_id' => $context->sales->tenant->getKey(),
        'branch_id' => $context->sales->branch->getKey(),
        'supplier_id' => $context->supplier->getKey(),
        'po_number' => 'PO-2026-99999',
        'status' => 'received',
        'order_date' => '2026-07-22',
    ]);
    $this->putJson("/api/v1/purchase-orders/{$received->getKey()}/cancel", [], ['Idempotency-Key' => purchasingKey()])
        ->assertConflict()
        ->assertHeader('Content-Type', 'application/problem+json');
});

it('receives goods atomically with exact stock lot and inventory GRNI journal', function () {
    $context = PurchasingContext::create();
    $orderId = createConfirmedOrder($context);
    $key = purchasingKey();

    $first = $this->postJson('/api/v1/goods-receipt-notes', $context->goodsReceiptPayload($orderId), [
        'Idempotency-Key' => $key,
    ])->assertCreated()
        ->assertJsonPath('data.grn_number', 'GRN-2026-00001');
    $grnId = (int) $first->json('data.id');

    $this->postJson('/api/v1/goods-receipt-notes', $context->goodsReceiptPayload($orderId), [
        'Idempotency-Key' => $key,
    ])->assertCreated()->assertJsonPath('data.id', $grnId);

    expect(GoodsReceiptNote::query()->count())->toBe(1)
        ->and(StockMovement::query()->where('type', 'PURCHASE_RECEIPT')->count())->toBe(1)
        ->and(StockLevel::query()->where('product_variant_id', $context->sales->variant->getKey())->value('quantity_on_hand'))->toBe('25.0000')
        ->and(InventoryLot::query()->where('product_variant_id', $context->sales->variant->getKey())->count())->toBe(2)
        ->and(PurchaseOrder::query()->findOrFail($orderId)->status->value)->toBe('received')
        ->and(purchasingJournalLines('grn', $grnId))->toMatchArray([
            '1300' => ['debit' => '21.25', 'credit' => '0.00'],
            '2050' => ['debit' => '0.00', 'credit' => '21.25'],
        ]);

    $changed = $context->goodsReceiptPayload($orderId);
    $changed['notes'] = 'Different';
    $this->postJson('/api/v1/goods-receipt-notes', $changed, ['Idempotency-Key' => $key])
        ->assertConflict()
        ->assertHeader('Content-Type', 'application/problem+json');
});

it('supports direct GRN replay and rejects conflicting payload hashes', function () {
    $context = PurchasingContext::create();
    $action = app(ProcessGoodsReceiptAction::class);
    $data = new GoodsReceiptData(
        $context->sales->branch->getKey(),
        $context->supplier->getKey(),
        null,
        new DateTimeImmutable('2026-07-22 10:00:00'),
        null,
        'direct-grn-key',
        hash('sha256', 'same'),
        [new GrnItemData($context->sales->variant->getKey(), '1.0000', '4.2500')],
    );

    $first = $action->handle($data, $context->sales->user->getKey());
    expect($action->handle($data, $context->sales->user->getKey())->is($first))->toBeTrue()
        ->and(GoodsReceiptNote::query()->count())->toBe(1);

    $conflict = new GoodsReceiptData(
        $data->branchId,
        $data->supplierId,
        null,
        $data->receivedAt,
        null,
        $data->idempotencyKey,
        hash('sha256', 'different'),
        $data->items,
    );
    expect(fn () => $action->handle($conflict, $context->sales->user->getKey()))
        ->toThrow(IdempotencyConflictException::class);
});

it('moves a purchase order through partial and final receipts', function () {
    $context = PurchasingContext::create();
    $orderId = createConfirmedOrder($context);

    $this->postJson('/api/v1/goods-receipt-notes', $context->goodsReceiptPayload($orderId, '2.0000'), [
        'Idempotency-Key' => purchasingKey(),
    ])->assertCreated();
    expect(PurchaseOrder::query()->findOrFail($orderId)->status->value)->toBe('partially_received');

    $this->postJson('/api/v1/goods-receipt-notes', $context->goodsReceiptPayload($orderId, '3.0000'), [
        'Idempotency-Key' => purchasingKey(),
    ])->assertCreated();
    $order = PurchaseOrder::query()->with('items')->findOrFail($orderId);
    expect($order->status->value)->toBe('received')
        ->and($order->items->first()->quantity_received)->toBe('5.0000');
});

it('rolls back every receipt side effect on over receipt and unit cost mismatch', function (string $quantity, string $cost) {
    $context = PurchasingContext::create();
    $orderId = createConfirmedOrder($context);
    $stockBefore = StockLevel::query()->where('product_variant_id', $context->sales->variant->getKey())->value('quantity_on_hand');
    $lotsBefore = InventoryLot::query()->count();

    $this->postJson('/api/v1/goods-receipt-notes', $context->goodsReceiptPayload($orderId, $quantity, $cost), [
        'Idempotency-Key' => purchasingKey(),
    ])->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/problem+json');

    expect(GoodsReceiptNote::query()->count())->toBe(0)
        ->and(StockMovement::query()->where('type', 'PURCHASE_RECEIPT')->count())->toBe(0)
        ->and(InventoryLot::query()->count())->toBe($lotsBefore)
        ->and(JournalEntry::query()->where('reference_type', 'grn')->count())->toBe(0)
        ->and(StockLevel::query()->where('product_variant_id', $context->sales->variant->getKey())->value('quantity_on_hand'))->toBe($stockBefore)
        ->and(PurchaseOrder::query()->with('items')->findOrFail($orderId)->items->first()->quantity_received)->toBe('0.0000');
})->with([
    'over receipt' => ['5.0001', '4.2500'],
    'unit cost mismatch' => ['5.0000', '4.2501'],
]);

it('creates approves and pays linked bills with exact snapshots and journals', function () {
    $context = PurchasingContext::create();
    $orderId = createConfirmedOrder($context);
    $grnId = (int) $this->postJson('/api/v1/goods-receipt-notes', $context->goodsReceiptPayload($orderId), [
        'Idempotency-Key' => purchasingKey(),
    ])->assertCreated()->json('data.id');

    $billResponse = $this->postJson('/api/v1/bills', $context->billPayload($grnId), ['Idempotency-Key' => purchasingKey()])
        ->assertCreated()
        ->assertJsonPath('data.total_amount', '22.84')
        ->assertJsonPath('data.tax_amount', '1.59')
        ->assertJsonPath('data.items.0.tax_rate_snapshot', '7.5000');
    $billId = (int) $billResponse->json('data.id');

    $this->putJson("/api/v1/bills/{$billId}/approve", [], ['Idempotency-Key' => purchasingKey()])
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'approved');
    expect(purchasingJournalLines('bill', $billId))->toMatchArray([
        '2050' => ['debit' => '21.25', 'credit' => '0.00'],
        '2100' => ['debit' => '1.59', 'credit' => '0.00'],
        '2000' => ['debit' => '0.00', 'credit' => '22.84'],
    ]);

    $paymentId = (int) $this->postJson("/api/v1/bills/{$billId}/payments", [
        'amount' => '10.00',
        'payment_method' => 'cash',
        'payment_date' => '2026-07-23',
    ], ['Idempotency-Key' => purchasingKey()])->assertCreated()->json('data.id');
    expect(Bill::query()->findOrFail($billId)->balance_due)->toBe('12.84')
        ->and(purchasingJournalLines('bill_payment', $paymentId))->toMatchArray([
            '2000' => ['debit' => '10.00', 'credit' => '0.00'],
            '1100' => ['debit' => '0.00', 'credit' => '10.00'],
        ]);

    $this->postJson("/api/v1/bills/{$billId}/payments", [
        'amount' => '12.85',
        'payment_method' => 'cash',
        'payment_date' => '2026-07-23',
    ], ['Idempotency-Key' => purchasingKey()])->assertUnprocessable();
    expect(BillPayment::query()->count())->toBe(1)
        ->and(Bill::query()->findOrFail($billId)->balance_due)->toBe('12.84');
    $this->putJson("/api/v1/bills/{$billId}/approve", [], ['Idempotency-Key' => purchasingKey()])
        ->assertConflict();
});

it('posts unlinked bills to purchase expense and rejects duplicate numbers and GRN mismatches', function () {
    $context = PurchasingContext::create();
    $this->actingAs($context->sales->user);
    $billId = (int) $this->postJson('/api/v1/bills', $context->billPayload(), ['Idempotency-Key' => purchasingKey()])
        ->assertCreated()->json('data.id');
    $this->putJson("/api/v1/bills/{$billId}/approve", [], ['Idempotency-Key' => purchasingKey()])
        ->assertSuccessful();
    expect(purchasingJournalLines('bill', $billId))->toMatchArray([
        '6000' => ['debit' => '21.25', 'credit' => '0.00'],
        '2100' => ['debit' => '1.59', 'credit' => '0.00'],
        '2000' => ['debit' => '0.00', 'credit' => '22.84'],
    ]);

    $this->postJson('/api/v1/bills', $context->billPayload(), ['Idempotency-Key' => purchasingKey()])
        ->assertUnprocessable();

    $orderId = createConfirmedOrder($context);
    $grnId = (int) $this->postJson('/api/v1/goods-receipt-notes', $context->goodsReceiptPayload($orderId), [
        'Idempotency-Key' => purchasingKey(),
    ])->assertCreated()->json('data.id');
    $this->postJson('/api/v1/bills', $context->billPayload($grnId, 'MISMATCH', '5.0000', '4.2501'), [
        'Idempotency-Key' => purchasingKey(),
    ])->assertUnprocessable();
    expect(Bill::query()->where('bill_number', 'MISMATCH')->count())->toBe(0);
});

it('processes supplier returns newest lot first and rolls back insufficient returns', function () {
    $context = PurchasingContext::create();
    InventoryLot::query()->delete();
    StockLevel::query()->where('product_variant_id', $context->sales->variant->getKey())->update(['quantity_on_hand' => '5.0000']);
    InventoryLot::query()->create([
        'product_variant_id' => $context->sales->variant->getKey(),
        'branch_id' => $context->sales->branch->getKey(),
        'quantity_remaining' => '2.0000',
        'unit_cost' => '2.0000',
        'received_at' => '2026-07-01',
    ]);
    $newest = InventoryLot::query()->create([
        'product_variant_id' => $context->sales->variant->getKey(),
        'branch_id' => $context->sales->branch->getKey(),
        'quantity_remaining' => '3.0000',
        'unit_cost' => '5.0000',
        'received_at' => '2026-07-02',
    ]);
    $action = app(ProcessSupplierReturnAction::class);
    $data = new SupplierReturnData(
        $context->sales->branch->getKey(),
        $context->supplier->getKey(),
        null,
        'Damaged shipment',
        'return-key',
        hash('sha256', 'return'),
        [new SupplierReturnItemData($context->sales->variant->getKey(), '4.0000')],
    );

    $return = $action->handle($data, $context->sales->user->getKey());
    expect($return->status->value)->toBe('approved')
        ->and($return->total_cost)->toBe('17.00')
        ->and($return->items->first()->unit_cost)->toBe('4.2500')
        ->and($newest->fresh()->quantity_remaining)->toBe('0.0000')
        ->and(StockMovement::query()->where('type', 'PURCHASE_RETURN')->firstOrFail()->quantity_delta)->toBe('-4.0000')
        ->and(purchasingJournalLines('supplier_return', $return->getKey()))->toMatchArray([
            '2050' => ['debit' => '17.00', 'credit' => '0.00'],
            '1300' => ['debit' => '0.00', 'credit' => '17.00'],
        ]);
    expect($action->handle($data, $context->sales->user->getKey())->is($return))->toBeTrue()
        ->and(SupplierReturn::query()->count())->toBe(1);

    $conflict = new SupplierReturnData(
        $data->branchId,
        $data->supplierId,
        null,
        $data->reason,
        $data->idempotencyKey,
        hash('sha256', 'conflict'),
        $data->items,
    );
    expect(fn () => $action->handle($conflict, $context->sales->user->getKey()))
        ->toThrow(IdempotencyConflictException::class);

    $before = StockLevel::query()->where('product_variant_id', $context->sales->variant->getKey())->value('quantity_on_hand');
    $insufficient = new SupplierReturnData(
        $data->branchId,
        $data->supplierId,
        null,
        'Too many',
        'return-insufficient',
        hash('sha256', 'insufficient'),
        [new SupplierReturnItemData($context->sales->variant->getKey(), '2.0000')],
    );
    expect(fn () => $action->handle($insufficient, $context->sales->user->getKey()))
        ->toThrow(InsufficientStockException::class);
    expect(SupplierReturn::query()->count())->toBe(1)
        ->and(StockLevel::query()->where('product_variant_id', $context->sales->variant->getKey())->value('quantity_on_hand'))->toBe($before);
});

it('reduces linked bill balance and debits AP for a supplier return', function () {
    $context = PurchasingContext::create();
    InventoryLot::query()->delete();
    StockLevel::query()->where('product_variant_id', $context->sales->variant->getKey())
        ->update(['quantity_on_hand' => '2.0000']);
    InventoryLot::query()->create([
        'product_variant_id' => $context->sales->variant->getKey(),
        'branch_id' => $context->sales->branch->getKey(),
        'quantity_remaining' => '2.0000',
        'unit_cost' => '4.0000',
        'received_at' => '2026-07-01',
    ]);
    $bill = Bill::query()->create([
        'branch_id' => $context->sales->branch->getKey(),
        'supplier_id' => $context->supplier->getKey(),
        'bill_number' => 'RETURN-BILL',
        'bill_date' => '2026-07-22',
        'status' => 'approved',
        'total_amount' => '20.00',
        'tax_amount' => '0.00',
        'balance_due' => '20.00',
    ]);
    $data = new SupplierReturnData(
        $context->sales->branch->getKey(),
        $context->supplier->getKey(),
        $bill->getKey(),
        'Supplier credit',
        'linked-return',
        hash('sha256', 'linked-return'),
        [new SupplierReturnItemData($context->sales->variant->getKey(), '1.0000')],
    );

    $return = app(ProcessSupplierReturnAction::class)->handle($data, $context->sales->user->getKey());
    expect($bill->fresh()->balance_due)->toBe('16.00')
        ->and($return->total_cost)->toBe('4.00')
        ->and(purchasingJournalLines('supplier_return', $return->getKey()))->toMatchArray([
            '2000' => ['debit' => '4.00', 'credit' => '0.00'],
            '1300' => ['debit' => '0.00', 'credit' => '4.00'],
        ]);
});

it('does not expose a supplier return API route', function () {
    $context = PurchasingContext::create();
    $this->actingAs($context->sales->user)
        ->postJson('/api/v1/supplier-returns', [])
        ->assertNotFound();
});

it('uses authoritative FIFO cost for sales movements snapshots and COGS', function () {
    $context = PurchasingContext::create();
    InventoryLot::query()->delete();
    StockLevel::query()->where('product_variant_id', $context->sales->variant->getKey())
        ->update(['quantity_on_hand' => '5.0000']);
    $oldest = InventoryLot::query()->create([
        'product_variant_id' => $context->sales->variant->getKey(),
        'branch_id' => $context->sales->branch->getKey(),
        'quantity_remaining' => '2.0000',
        'unit_cost' => '2.0000',
        'received_at' => '2026-07-01',
    ]);
    $newest = InventoryLot::query()->create([
        'product_variant_id' => $context->sales->variant->getKey(),
        'branch_id' => $context->sales->branch->getKey(),
        'quantity_remaining' => '3.0000',
        'unit_cost' => '5.0000',
        'received_at' => '2026-07-02',
    ]);
    $payload = $context->sales->invoicePayload([[
        'variant_id' => $context->sales->variant->getKey(),
        'quantity' => '4.0000',
        'unit_price' => '10.0000',
    ]]);

    $invoiceId = (int) $this->actingAs($context->sales->user)
        ->postJson('/api/v1/invoices', $payload, ['Idempotency-Key' => purchasingKey()])
        ->assertCreated()->json('data.id');
    $item = InvoiceItem::query()->where('invoice_id', $invoiceId)->firstOrFail();
    $movement = StockMovement::query()->where('source_type', 'invoice')->where('source_id', $invoiceId)->firstOrFail();

    expect($item->cost_price_at_sale)->toBe('3.5000')
        ->and($item->cost_total_at_sale)->toBe('14.00')
        ->and($movement->unit_cost)->toBe('3.5000')
        ->and($oldest->fresh()->quantity_remaining)->toBe('0.0000')
        ->and($newest->fresh()->quantity_remaining)->toBe('1.0000')
        ->and(purchasingJournalLines('invoice', $invoiceId))->toMatchArray([
            '5000' => ['debit' => '14.00', 'credit' => '0.00'],
            '1300' => ['debit' => '0.00', 'credit' => '14.00'],
        ]);
});
