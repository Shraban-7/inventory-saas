<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->uuid('idempotency_key');
            $table->string('payload_hash', 64);
            $table->decimal('quantity_delta', 15, 4);
            $table->enum('type', ['STOCK_ADJUSTMENT_IN', 'STOCK_ADJUSTMENT_OUT']);
            $table->string('reason');
            $table->timestamps();
            $table->unique(['tenant_id', 'idempotency_key']);
        });

        Schema::create('stock_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('to_branch_id')->constrained('branches')->restrictOnDelete();
            $table->uuid('idempotency_key');
            $table->string('payload_hash', 64);
            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('pending');
            $table->timestamp('transferred_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'idempotency_key']);
        });

        Schema::create('stock_transfer_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 4);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('stock_adjustments');
    }
};
