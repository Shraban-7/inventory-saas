<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('webhook_deliveries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('occurrence_id');
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('webhook_endpoint_id');
            $table->foreign('webhook_endpoint_id')
                ->references('id')
                ->on('webhook_endpoints')
                ->restrictOnDelete();
            $table->string('event', 64);
            $table->longText('payload');
            $table->enum('status', ['pending', 'delivered', 'failed'])->default('pending');
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->string('error_detail', 1000)->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->unique(
                ['tenant_id', 'webhook_endpoint_id', 'occurrence_id'],
                'webhook_deliveries_occurrence_unique',
            );
            $table->index(
                ['tenant_id', 'status', 'next_retry_at'],
                'webhook_deliveries_pending_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
