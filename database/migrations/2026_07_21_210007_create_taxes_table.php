<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('coa_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->string('name');
            $table->decimal('rate', 7, 4);
            $table->boolean('is_compound')->default(false);
            $table->timestamps();
            $table->index(['tenant_id', 'coa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxes');
    }
};
