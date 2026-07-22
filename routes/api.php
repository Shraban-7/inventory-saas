<?php

use App\Presentation\Controllers\AccountingPeriodController;
use App\Presentation\Controllers\BillController;
use App\Presentation\Controllers\BulkImportController;
use App\Presentation\Controllers\ChartOfAccountController;
use App\Presentation\Controllers\CreditNoteController;
use App\Presentation\Controllers\CustomerController;
use App\Presentation\Controllers\GoodsReceiptNoteController;
use App\Presentation\Controllers\InvoiceController;
use App\Presentation\Controllers\JournalEntryController;
use App\Presentation\Controllers\ProductController;
use App\Presentation\Controllers\PurchaseOrderController;
use App\Presentation\Controllers\ReportController;
use App\Presentation\Controllers\StockAdjustmentController;
use App\Presentation\Controllers\StockTransferController;
use App\Presentation\Controllers\SupplierController;
use App\Presentation\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['auth', 'tenant'])
    ->group(function (): void {
        Route::get('customers', [CustomerController::class, 'index'])->middleware('can:invoice.view');
        Route::post('customers', [CustomerController::class, 'store'])->middleware('can:invoice.create');
        Route::get('customers/{customerId}', [CustomerController::class, 'show'])->middleware('can:invoice.view');
        Route::put('customers/{customerId}', [CustomerController::class, 'update'])->middleware('can:invoice.create');

        Route::get('invoices', [InvoiceController::class, 'index'])->middleware('can:invoice.view');
        Route::post('invoices', [InvoiceController::class, 'store'])
            ->middleware(['can:invoice.create', 'idempotency']);
        Route::get('invoices/{invoiceId}', [InvoiceController::class, 'show'])->middleware('can:invoice.view');
        Route::put('invoices/{invoiceId}/void', [InvoiceController::class, 'void'])
            ->middleware(['can:invoice.void', 'idempotency']);
        Route::post('invoices/{invoiceId}/receipts', [InvoiceController::class, 'receipt'])
            ->middleware(['can:invoice.create', 'idempotency']);

        Route::get('credit-notes', [CreditNoteController::class, 'index'])->middleware('can:invoice.view');
        Route::post('credit-notes', [CreditNoteController::class, 'store'])
            ->middleware(['can:invoice.void', 'idempotency']);
        Route::put('credit-notes/{creditNoteId}/approve', [CreditNoteController::class, 'approve'])
            ->middleware(['can:invoice.void', 'idempotency']);

        Route::get('products', [ProductController::class, 'index']);
        Route::post('products', [ProductController::class, 'store'])->middleware('can:product.manage');
        Route::get('products/{productId}/variants', [ProductController::class, 'variants']);
        Route::post('products/{productId}/variants', [ProductController::class, 'addVariant'])->middleware('can:product.manage');
        Route::get('products/{productId}/stock', [ProductController::class, 'stock']);

        Route::post('stock-adjustments', [StockAdjustmentController::class, 'store'])
            ->middleware(['can:stock.adjust', 'idempotency']);
        Route::post('stock-transfers', [StockTransferController::class, 'store'])
            ->middleware(['can:stock.transfer', 'idempotency']);

        Route::post('bulk/products', [BulkImportController::class, 'products'])
            ->middleware(['can:product.manage', 'throttle:bulk-imports']);
        Route::post('bulk/stock-adjustments', [BulkImportController::class, 'stockAdjustments'])
            ->middleware(['can:stock.adjust', 'throttle:bulk-imports']);
        Route::get('bulk/imports/{bulkImportId}', [BulkImportController::class, 'show'])
            ->name('bulk.imports.show');
        Route::get('bulk/imports/{bulkImportId}/errors', [BulkImportController::class, 'errors'])
            ->name('bulk.imports.errors');

        Route::get('webhooks', [WebhookController::class, 'index']);
        Route::post('webhooks', [WebhookController::class, 'store']);
        Route::delete('webhooks/{webhookEndpointId}', [WebhookController::class, 'destroy']);

        Route::get('suppliers', [SupplierController::class, 'index'])->middleware('can:purchase.create');
        Route::post('suppliers', [SupplierController::class, 'store'])->middleware('can:purchase.create');
        Route::get('suppliers/{supplierId}', [SupplierController::class, 'show'])->middleware('can:purchase.create');
        Route::put('suppliers/{supplierId}', [SupplierController::class, 'update'])->middleware('can:purchase.create');

        Route::get('purchase-orders', [PurchaseOrderController::class, 'index'])->middleware('can:purchase.create');
        Route::post('purchase-orders', [PurchaseOrderController::class, 'store'])
            ->middleware(['can:purchase.create', 'idempotency']);
        Route::put('purchase-orders/{purchaseOrderId}/confirm', [PurchaseOrderController::class, 'confirm'])
            ->middleware(['can:purchase.create', 'idempotency']);
        Route::put('purchase-orders/{purchaseOrderId}/cancel', [PurchaseOrderController::class, 'cancel'])
            ->middleware(['can:purchase.create', 'idempotency']);

        Route::get('goods-receipt-notes', [GoodsReceiptNoteController::class, 'index'])->middleware('can:purchase.receive');
        Route::post('goods-receipt-notes', [GoodsReceiptNoteController::class, 'store'])
            ->middleware(['can:purchase.receive', 'idempotency']);

        Route::get('bills', [BillController::class, 'index'])->middleware('can:purchase.create');
        Route::post('bills', [BillController::class, 'store'])
            ->middleware(['can:purchase.create', 'idempotency']);
        Route::put('bills/{billId}/approve', [BillController::class, 'approve'])
            ->middleware(['can:purchase.create', 'idempotency']);
        Route::post('bills/{billId}/payments', [BillController::class, 'payment'])
            ->middleware(['can:purchase.create', 'idempotency']);

        Route::get('chart-of-accounts', [ChartOfAccountController::class, 'index'])
            ->middleware('can:report.view');

        Route::get('journal-entries', [JournalEntryController::class, 'index'])
            ->middleware('can:report.view');
        Route::get('journal-entries/{journalEntryId}', [JournalEntryController::class, 'show'])
            ->middleware('can:report.view');
        Route::post('journal-entries', [JournalEntryController::class, 'store'])
            ->middleware(['can:report.view', 'idempotency']);

        Route::post('reports/profit-and-loss', [ReportController::class, 'storeProfitAndLoss'])
            ->middleware(['can:report.view', 'idempotency'])
            ->name('reports.profit-and-loss.store');
        Route::get('reports/jobs/{reportJobId}', [ReportController::class, 'show'])
            ->middleware('can:report.view')
            ->name('reports.jobs.show');
        Route::get('reports/jobs/{reportJobId}/result', [ReportController::class, 'result'])
            ->middleware('can:report.view')
            ->name('reports.jobs.result');

        Route::put('accounting-periods/{accountingPeriodId}/lock', [AccountingPeriodController::class, 'lock'])
            ->middleware('idempotency');
    });
