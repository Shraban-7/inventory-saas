<?php

use App\Presentation\Controllers\ProductController;
use App\Presentation\Controllers\StockAdjustmentController;
use App\Presentation\Controllers\StockTransferController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['auth', 'tenant'])
    ->group(function (): void {
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
