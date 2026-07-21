<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the audit_logs table.
 *
 * Architecture ref: database-design.md §7 System & Audit Context
 *
 * Design decisions:
 * - append-only: no updated_at column. Corrections are new rows, not edits.
 * - old_values / new_values: JSON snapshots of the entity state before and after.
 *   Required for financial forensics and fraud investigation.
 * - ip_address, user_agent, session_id: forensic metadata required for compliance.
 * - polymorphic (entity_type / entity_id): single table covers all domain entities.
 *   Integrity enforced via application-level MorphMap, not DB-level FK.
 *
 * Indexes:
 * - (tenant_id, entity_type, entity_id, created_at): primary lookup pattern for
 *   "show me the full history of this entity".
 * - (tenant_id, user_id, created_at): secondary pattern for
 *   "show me everything this user did".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();

            // Tenant & user context
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // What happened
            $table->string('action', 60);  // e.g. 'created', 'updated', 'voided', 'STOCK_DRIFT_DETECTED'

            // Which entity changed (polymorphic — no DB FK, enforced via MorphMap)
            $table->string('entity_type', 100);
            $table->unsignedBigInteger('entity_id');

            // Before / after snapshot (required for financial compliance)
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            // Forensic metadata
            $table->string('ip_address', 45)->nullable();   // supports IPv6
            $table->text('user_agent')->nullable();
            $table->string('session_id', 40)->nullable();

            // Append-only: created_at only, no updated_at
            $table->timestamp('created_at')->useCurrent();
        });

        // Primary forensic index: full history of a specific entity
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->index(
                ['tenant_id', 'entity_type', 'entity_id', 'created_at'],
                'audit_logs_entity_timeline_index',
            );

            // Secondary index: all actions by a specific user
            $table->index(
                ['tenant_id', 'user_id', 'created_at'],
                'audit_logs_user_timeline_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
