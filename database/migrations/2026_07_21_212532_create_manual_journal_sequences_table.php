<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_journal_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->smallInteger('year');
            $table->unsignedBigInteger('sequence')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_journal_sequences');
    }
};
