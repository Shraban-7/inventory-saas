<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchasing_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->enum('document_type', ['po', 'grn']);
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('sequence')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'document_type', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchasing_sequences');
    }
};
