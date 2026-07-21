<?php

use Illuminate\Database\Eloquent\Relations\Relation;

it('resolves every approved polymorphic alias', function (string $alias, string $model) {
    expect(Relation::getMorphedModel($alias))->toBe($model);
})->with([
    'invoice' => ['invoice', 'App\\Infrastructure\\Models\\Invoice'],
    'credit note' => ['credit_note', 'App\\Infrastructure\\Models\\CreditNote'],
    'bill' => ['bill', 'App\\Infrastructure\\Models\\Bill'],
    'goods receipt note' => ['grn', 'App\\Infrastructure\\Models\\GoodsReceiptNote'],
    'supplier' => ['supplier', 'App\\Infrastructure\\Models\\Supplier'],
    'purchase order' => ['purchase_order', 'App\\Infrastructure\\Models\\PurchaseOrder'],
    'supplier return' => ['supplier_return', 'App\\Infrastructure\\Models\\SupplierReturn'],
    'bill payment' => ['bill_payment', 'App\\Infrastructure\\Models\\BillPayment'],
    'stock transfer' => ['stock_transfer', 'App\\Infrastructure\\Models\\StockTransfer'],
    'stock adjustment' => ['adjustment', 'App\\Infrastructure\\Models\\StockAdjustment'],
]);
