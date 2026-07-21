<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipt_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('grn_number');
            $table->uuid('idempotency_key');
            $table->string('payload_hash', 64);
            $table->timestamp('received_at');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'grn_number']);
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['tenant_id', 'branch_id', 'received_at'], 'grns_branch_received_index');
            $table->index(['tenant_id', 'supplier_id', 'received_at'], 'grns_supplier_received_index');
            $table->index(['tenant_id', 'purchase_order_id']);
        });

        Schema::create('grn_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('grn_id')->constrained('goods_receipt_notes')->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_received', 15, 4);
            $table->decimal('unit_cost', 15, 4);
            $table->timestamps();
            $table->unique(['grn_id', 'product_variant_id']);
            $table->index(['tenant_id', 'grn_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grn_items');
        Schema::dropIfExists('goods_receipt_notes');
    }
};
