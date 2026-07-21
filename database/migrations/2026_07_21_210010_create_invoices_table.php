<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('invoice_number');
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->enum('status', ['draft', 'issued', 'paid', 'partially_paid', 'voided'])->default('issued');
            $table->decimal('total_amount', 15, 2);
            $table->decimal('tax_amount', 15, 2);
            $table->decimal('balance_due', 15, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'invoice_number']);
            $table->index(['tenant_id', 'branch_id', 'invoice_date'], 'invoices_branch_date_index');
            $table->index(['tenant_id', 'status', 'invoice_date'], 'invoices_status_date_index');
            $table->index(['tenant_id', 'customer_id', 'invoice_date'], 'invoices_customer_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
