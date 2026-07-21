<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('bill_id')->nullable()->constrained()->restrictOnDelete();
            $table->uuid('idempotency_key');
            $table->string('payload_hash', 64);
            $table->string('reason');
            $table->enum('status', ['draft', 'approved'])->default('draft');
            $table->decimal('total_cost', 15, 2)->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['tenant_id', 'branch_id', 'status'], 'supplier_returns_branch_status_index');
            $table->index(['tenant_id', 'supplier_id']);
            $table->index(['tenant_id', 'bill_id']);
        });

        Schema::create('supplier_return_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_return_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_cost', 15, 4);
            $table->timestamps();
            $table->unique(
                ['supplier_return_id', 'product_variant_id'],
                'supplier_return_items_return_variant_unique',
            );
            $table->index(['tenant_id', 'supplier_return_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_return_items');
        Schema::dropIfExists('supplier_returns');
    }
};
