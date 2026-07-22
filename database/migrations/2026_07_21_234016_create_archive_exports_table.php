<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archive_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('dataset', 64);
            $table->unsignedSmallInteger('period_year');
            $table->string('schema_version', 32);
            $table->string('status', 32)->default('pending');
            $table->string('disk', 64)->nullable();
            $table->string('path', 512)->nullable();
            $table->json('manifest')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->unsignedBigInteger('row_count')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'dataset', 'period_year', 'schema_version'],
                'archive_exports_idempotency_unique',
            );
            $table->index(
                ['tenant_id', 'status', 'period_year'],
                'archive_exports_status_period_index',
            );
            $table->index(
                ['tenant_id', 'dataset', 'status'],
                'archive_exports_dataset_status_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archive_exports');
    }
};
