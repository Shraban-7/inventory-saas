<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table): void {
            $table->index(
                ['tenant_id', 'posted_at', 'id'],
                'journal_entries_posted_read_index',
            );
            $table->index(
                ['tenant_id', 'reference_type', 'reference_id', 'id'],
                'journal_entries_reference_read_index',
            );
        });
    }

    public function down(): void
    {
        if (Schema::hasIndex('journal_entries', 'journal_entries_reference_read_index')) {
            Schema::table('journal_entries', function (Blueprint $table): void {
                $table->dropIndex('journal_entries_reference_read_index');
            });
        }

        if (Schema::hasIndex('journal_entries', 'journal_entries_posted_read_index')) {
            Schema::table('journal_entries', function (Blueprint $table): void {
                $table->dropIndex('journal_entries_posted_read_index');
            });
        }
    }
};
