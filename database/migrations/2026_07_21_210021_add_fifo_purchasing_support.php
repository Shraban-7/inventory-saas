<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_lots', function (Blueprint $table): void {
            $table->index(
                ['tenant_id', 'product_variant_id', 'branch_id', 'received_at', 'id'],
                'inventory_lots_fifo_index',
            );
        });

        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->decimal('cost_total_at_sale', 15, 2)->nullable();
        });

        Schema::table('credit_note_items', function (Blueprint $table): void {
            $table->decimal('cost_total_at_return', 15, 2)->nullable();
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->unique(
                ['tenant_id', 'source_type', 'source_id', 'product_variant_id', 'branch_id', 'type'],
                'stock_movements_source_identity_unique',
            );
        });
    }

    public function down(): void
    {
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)
            && ! Schema::hasIndex('stock_movements', 'stock_movements_tenant_id_index')) {
            Schema::table('stock_movements', function (Blueprint $table): void {
                $table->index('tenant_id', 'stock_movements_tenant_id_index');
            });
        }

        if (Schema::hasIndex('stock_movements', 'stock_movements_source_identity_unique')) {
            Schema::table('stock_movements', function (Blueprint $table): void {
                $table->dropUnique('stock_movements_source_identity_unique');
            });
        }

        if (Schema::hasColumn('credit_note_items', 'cost_total_at_return')) {
            Schema::table('credit_note_items', function (Blueprint $table): void {
                $table->dropColumn('cost_total_at_return');
            });
        }

        if (Schema::hasColumn('invoice_items', 'cost_total_at_sale')) {
            Schema::table('invoice_items', function (Blueprint $table): void {
                $table->dropColumn('cost_total_at_sale');
            });
        }

        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)
            && ! Schema::hasIndex('inventory_lots', 'inventory_lots_tenant_id_index')) {
            Schema::table('inventory_lots', function (Blueprint $table): void {
                $table->index('tenant_id', 'inventory_lots_tenant_id_index');
            });
        }

        if (Schema::hasIndex('inventory_lots', 'inventory_lots_fifo_index')) {
            Schema::table('inventory_lots', function (Blueprint $table): void {
                $table->dropIndex('inventory_lots_fifo_index');
            });
        }
    }
};
