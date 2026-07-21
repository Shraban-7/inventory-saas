<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the missing operational columns to the branches table.
 *
 * Architecture ref: database-design.md §2 IAM & Tenancy Context
 * - address: physical location for receipts and display
 * - phone: contact number for the branch
 * - is_primary: flags the tenant's default/head branch
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->text('address')->nullable()->after('name');
            $table->string('phone', 30)->nullable()->after('address');
            $table->boolean('is_primary')->default(false)->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->dropColumn(['address', 'phone', 'is_primary']);
        });
    }
};
