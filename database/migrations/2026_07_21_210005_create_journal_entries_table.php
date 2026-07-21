<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('journal_entry_number');
            $table->text('description');
            $table->nullableMorphs('reference');
            $table->date('posted_at');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['tenant_id', 'journal_entry_number']);
            $table->index(['tenant_id', 'branch_id', 'posted_at'], 'journal_entries_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
