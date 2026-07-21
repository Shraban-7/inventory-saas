<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_account_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('coa_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->date('date');
            $table->decimal('debit_total', 15, 2)->default(0);
            $table->decimal('credit_total', 15, 2)->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'branch_id', 'coa_id', 'date']);
            $table->index(
                ['tenant_id', 'date', 'branch_id', 'coa_id'],
                'daily_account_balances_reporting_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_account_balances');
    }
};
