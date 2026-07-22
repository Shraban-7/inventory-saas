<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_imports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->enum('type', ['products', 'stock_adjustments']);
            $table->enum('status', ['queued', 'running', 'completed', 'failed'])->default('queued');
            $table->string('disk');
            $table->string('path', 1024);
            $table->uuid('batch_id')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('succeeded_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->string('error_code')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(
                ['tenant_id', 'requested_by_user_id', 'status'],
                'bulk_imports_requester_status_index',
            );
            $table->index(['tenant_id', 'status', 'created_at'], 'bulk_imports_status_index');
            $table->index('batch_id');
        });

        Schema::create('bulk_import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('bulk_import_id');
            $table->unsignedInteger('row_number');
            $table->enum('status', ['pending', 'succeeded', 'failed', 'skipped'])->default('pending');
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('bulk_import_id')->references('id')->on('bulk_imports')->cascadeOnDelete();
            $table->unique(['bulk_import_id', 'row_number'], 'bulk_import_rows_import_row_unique');
            $table->index(
                ['tenant_id', 'bulk_import_id', 'status', 'row_number'],
                'bulk_import_rows_error_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_import_rows');
        Schema::dropIfExists('bulk_imports');
    }
};
