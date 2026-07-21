<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_levels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity_on_hand', 15, 4)->default(0);
            $table->unique(['product_variant_id', 'branch_id']);
            $table->index(['tenant_id', 'product_variant_id', 'branch_id'], 'stock_levels_lookup_index');
        });

        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->enum('type', [
                'PURCHASE_RECEIPT', 'SALES_DEDUCTION', 'SALES_RETURN',
                'PURCHASE_RETURN', 'STOCK_ADJUSTMENT_IN', 'STOCK_ADJUSTMENT_OUT',
                'TRANSFER_OUT', 'TRANSFER_IN', 'OPENING_BALANCE',
            ]);
            $table->decimal('quantity_delta', 15, 4);
            $table->decimal('unit_cost', 15, 4)->nullable();
            $table->nullableMorphs('source');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['product_variant_id', 'branch_id', 'created_at'], 'stock_movements_variant_index');
        });

        Schema::create('inventory_lots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_remaining', 15, 4);
            $table->decimal('unit_cost', 15, 4);
            $table->timestamp('received_at');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_lots');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_levels');
    }
};
