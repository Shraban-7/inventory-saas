<?php

use App\Presentation\Controllers\CreditNoteController;
use App\Presentation\Controllers\CustomerController;
use App\Presentation\Controllers\InvoiceController;
use App\Presentation\Controllers\ProductController;
use App\Presentation\Controllers\StockAdjustmentController;
use App\Presentation\Controllers\StockTransferController;
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
    });
