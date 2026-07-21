<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('grn_id')->nullable()->constrained('goods_receipt_notes')->restrictOnDelete();
            $table->string('bill_number');
            $table->date('bill_date');
            $table->date('due_date')->nullable();
            $table->enum('status', ['draft', 'approved', 'paid', 'partially_paid', 'cancelled'])->default('draft');
            $table->decimal('total_amount', 15, 2);
            $table->decimal('tax_amount', 15, 2);
            $table->decimal('balance_due', 15, 2);
            $table->timestamps();
            $table->unique(['tenant_id', 'bill_number']);
            $table->index(['tenant_id', 'branch_id', 'status', 'bill_date'], 'bills_branch_status_date_index');
            $table->index(['tenant_id', 'supplier_id', 'bill_date'], 'bills_supplier_date_index');
            $table->index(['tenant_id', 'grn_id']);
        });

        Schema::create('bill_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('bill_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('tax_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_cost', 15, 4);
            $table->decimal('tax_rate_snapshot', 7, 4)->nullable();
            $table->decimal('line_total', 15, 2);
            $table->timestamps();
            $table->unique(['bill_id', 'product_variant_id']);
            $table->index(['tenant_id', 'bill_id']);
        });

        Schema::create('bill_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('bill_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->enum('payment_method', ['cash', 'bank_transfer', 'cheque', 'other']);
            $table->date('payment_date');
            $table->string('reference')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'branch_id', 'payment_date'], 'bill_payments_branch_date_index');
            $table->index(['tenant_id', 'supplier_id', 'payment_date'], 'bill_payments_supplier_date_index');
            $table->index(['tenant_id', 'bill_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_payments');
        Schema::dropIfExists('bill_items');
        Schema::dropIfExists('bills');
    }
};
