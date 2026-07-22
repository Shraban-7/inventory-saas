<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bulk_import_rows', function (Blueprint $table): void {
            $table->string('target_key')->nullable()->after('row_number');
            $table->index(
                ['tenant_id', 'bulk_import_id', 'target_key'],
                'bulk_import_rows_target_key_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('bulk_import_rows', function (Blueprint $table): void {
            $table->dropIndex('bulk_import_rows_target_key_index');
            $table->dropColumn('target_key');
        });
    }
};
