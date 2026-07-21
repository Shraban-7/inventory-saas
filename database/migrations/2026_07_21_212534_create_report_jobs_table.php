<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_jobs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('requested_branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->enum('type', ['profit_and_loss']);
            $table->enum('status', ['queued', 'running', 'completed', 'failed', 'expired']);
            $table->json('parameters');
            $table->string('parameter_hash', 64);
            $table->json('result')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(
                ['tenant_id', 'requested_by_user_id', 'status'],
                'report_jobs_requester_status_index',
            );
            $table->index(['tenant_id', 'status', 'created_at'], 'report_jobs_status_index');
            $table->index(['tenant_id', 'expires_at'], 'report_jobs_expiry_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_jobs');
    }
};
