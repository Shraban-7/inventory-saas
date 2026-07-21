<?php

use App\Infrastructure\Models\Bill;
use App\Infrastructure\Models\GoodsReceiptNote;
use App\Infrastructure\Models\Permission;
use App\Infrastructure\Models\PurchaseOrder;
use App\Infrastructure\Models\Role;
use Illuminate\Support\Str;
use Tests\Support\PurchasingContext;

afterEach(fn () => app()->forgetInstance('current_tenant'));

function securityPurchasingKey(): string
{
    return (string) Str::uuid();
}

it('requires authentication on every purchasing API surface', function (string $method, string $uri) {
    $this->json($method, $uri, [], ['Idempotency-Key' => securityPurchasingKey()])
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/problem+json');
})->with([
    'suppliers list' => ['GET', '/api/v1/suppliers'],
    'supplier create' => ['POST', '/api/v1/suppliers'],
    'purchase orders list' => ['GET', '/api/v1/purchase-orders'],
    'purchase order create' => ['POST', '/api/v1/purchase-orders'],
    'goods receipts list' => ['GET', '/api/v1/goods-receipt-notes'],
    'goods receipt create' => ['POST', '/api/v1/goods-receipt-notes'],
    'bills list' => ['GET', '/api/v1/bills'],
    'bill create' => ['POST', '/api/v1/bills'],
]);

it('separates purchase creation from goods receiving permission', function () {
    $context = PurchasingContext::create();
    $context->sales->user->roles()->detach();
    $role = Role::query()->create([
        'tenant_id' => $context->sales->tenant->getKey(),
        'name' => 'Buyer',
        'guard_name' => 'web',
    ]);
    $role->syncPermissions([Permission::findByName('purchase.create')]);
    $context->sales->user->assignRole($role);

    $this->actingAs($context->sales->user)
        ->postJson('/api/v1/purchase-orders', $context->purchaseOrderPayload(), [
            'Idempotency-Key' => securityPurchasingKey(),
        ])->assertCreated();
    $this->postJson('/api/v1/goods-receipt-notes', $context->goodsReceiptPayload(), [
        'Idempotency-Key' => securityPurchasingKey(),
    ])->assertForbidden()
        ->assertHeader('Content-Type', 'application/problem+json');
});

it('denies cashier and accountant access to purchasing operations', function (string $role) {
    $context = PurchasingContext::create($role);
    $this->actingAs($context->sales->user)
        ->getJson('/api/v1/suppliers')
        ->assertForbidden();
    $this->postJson('/api/v1/purchase-orders', $context->purchaseOrderPayload(), [
        'Idempotency-Key' => securityPurchasingKey(),
    ])->assertForbidden();
    $this->postJson('/api/v1/goods-receipt-notes', $context->goodsReceiptPayload(), [
        'Idempotency-Key' => securityPurchasingKey(),
    ])->assertForbidden();
    $this->postJson('/api/v1/bills', $context->billPayload(), [
        'Idempotency-Key' => securityPurchasingKey(),
    ])->assertForbidden();
})->with(['Cashier', 'Accountant']);

it('rejects foreign tenant identifiers at purchasing boundaries', function (string $boundary) {
    $tenantA = PurchasingContext::create();
    $tenantB = PurchasingContext::create();
    $this->actingAs($tenantA->sales->user);

    if ($boundary === 'supplier') {
        $payload = $tenantA->purchaseOrderPayload();
        $payload['supplier_id'] = $tenantB->supplier->getKey();
        $response = $this->postJson('/api/v1/purchase-orders', $payload, ['Idempotency-Key' => securityPurchasingKey()]);
    } elseif ($boundary === 'branch') {
        $payload = $tenantA->purchaseOrderPayload();
        $payload['branch_id'] = $tenantB->sales->branch->getKey();
        $response = $this->postJson('/api/v1/purchase-orders', $payload, ['Idempotency-Key' => securityPurchasingKey()]);
    } elseif ($boundary === 'variant') {
        $payload = $tenantA->purchaseOrderPayload();
        $payload['items'][0]['variant_id'] = $tenantB->sales->variant->getKey();
        $response = $this->postJson('/api/v1/purchase-orders', $payload, ['Idempotency-Key' => securityPurchasingKey()]);
    } elseif ($boundary === 'tax') {
        $payload = $tenantA->billPayload();
        $payload['items'][0]['tax_id'] = $tenantB->sales->tax->getKey();
        $response = $this->postJson('/api/v1/bills', $payload, ['Idempotency-Key' => securityPurchasingKey()]);
    } else {
        throw new LogicException('Unknown purchasing security boundary.');
    }

    expect($response->getStatusCode())->toBeIn([404, 422]);
    $response->assertHeader('Content-Type', 'application/problem+json');
})->with(['supplier', 'branch', 'variant', 'tax']);

it('hides and blocks foreign suppliers purchase orders goods receipts and bills', function () {
    $tenantA = PurchasingContext::create();
    $tenantB = PurchasingContext::create();
    $orderB = PurchaseOrder::query()->create([
        'tenant_id' => $tenantB->sales->tenant->getKey(),
        'branch_id' => $tenantB->sales->branch->getKey(),
        'supplier_id' => $tenantB->supplier->getKey(),
        'po_number' => 'PO-2026-70001',
        'status' => 'draft',
        'order_date' => '2026-07-22',
    ]);
    $grnB = GoodsReceiptNote::query()->create([
        'tenant_id' => $tenantB->sales->tenant->getKey(),
        'branch_id' => $tenantB->sales->branch->getKey(),
        'supplier_id' => $tenantB->supplier->getKey(),
        'grn_number' => 'GRN-2026-70001',
        'idempotency_key' => securityPurchasingKey(),
        'payload_hash' => hash('sha256', 'foreign'),
        'received_at' => '2026-07-22',
    ]);
    $billB = Bill::query()->create([
        'tenant_id' => $tenantB->sales->tenant->getKey(),
        'branch_id' => $tenantB->sales->branch->getKey(),
        'supplier_id' => $tenantB->supplier->getKey(),
        'bill_number' => 'FOREIGN-1',
        'bill_date' => '2026-07-22',
        'status' => 'draft',
        'total_amount' => '1.00',
        'tax_amount' => '0.00',
        'balance_due' => '1.00',
    ]);

    $this->actingAs($tenantA->sales->user)
        ->getJson("/api/v1/suppliers/{$tenantB->supplier->getKey()}")
        ->assertNotFound();
    $this->putJson("/api/v1/purchase-orders/{$orderB->getKey()}/confirm", [], [
        'Idempotency-Key' => securityPurchasingKey(),
    ])->assertNotFound();
    $this->putJson("/api/v1/bills/{$billB->getKey()}/approve", [], [
        'Idempotency-Key' => securityPurchasingKey(),
    ])->assertNotFound();
    $this->getJson('/api/v1/purchase-orders')->assertJsonMissing(['id' => $orderB->getKey()]);
    $this->getJson('/api/v1/goods-receipt-notes')->assertJsonMissing(['id' => $grnB->getKey()]);
    $this->getJson('/api/v1/bills')->assertJsonMissing(['id' => $billB->getKey()]);
});

it('restricts branch scoped purchasing roles to their authorized branch', function () {
    $context = PurchasingContext::create('Manager');
    $manager = Role::query()->where('name', 'Manager')->firstOrFail();
    $context->sales->user->roles()->detach();
    $context->sales->user->roles()->attach($manager->getKey(), [
        'branch_id' => $context->sales->branch->getKey(),
    ]);
    $this->actingAs($context->sales->user);

    $allowedId = (int) $this->postJson('/api/v1/purchase-orders', $context->purchaseOrderPayload(), [
        'Idempotency-Key' => securityPurchasingKey(),
    ])->assertCreated()->json('data.id');
    $otherPayload = $context->purchaseOrderPayload();
    $otherPayload['branch_id'] = $context->sales->otherBranch->getKey();
    $this->postJson('/api/v1/purchase-orders', $otherPayload, [
        'Idempotency-Key' => securityPurchasingKey(),
    ])->assertForbidden();

    PurchaseOrder::query()->create([
        'tenant_id' => $context->sales->tenant->getKey(),
        'branch_id' => $context->sales->otherBranch->getKey(),
        'supplier_id' => $context->supplier->getKey(),
        'po_number' => 'PO-2026-90000',
        'status' => 'draft',
        'order_date' => '2026-07-22',
    ]);
    $this->getJson('/api/v1/purchase-orders')
        ->assertSuccessful()
        ->assertJsonFragment(['id' => $allowedId])
        ->assertJsonMissing(['po_number' => 'PO-2026-90000']);
    $this->postJson('/api/v1/goods-receipt-notes', $context->goodsReceiptPayload(), [
        'Idempotency-Key' => securityPurchasingKey(),
    ])->assertCreated();
    $foreignReceipt = $context->goodsReceiptPayload();
    $foreignReceipt['branch_id'] = $context->sales->otherBranch->getKey();
    $this->postJson('/api/v1/goods-receipt-notes', $foreignReceipt, [
        'Idempotency-Key' => securityPurchasingKey(),
    ])->assertForbidden();
});
