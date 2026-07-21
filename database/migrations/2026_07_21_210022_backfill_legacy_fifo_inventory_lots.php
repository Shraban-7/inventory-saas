<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('stock_levels')
            ->join('product_variants', 'product_variants.id', '=', 'stock_levels.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->where('stock_levels.quantity_on_hand', '>', 0)
            ->where('products.costing_method', 'fifo')
            ->whereNotExists(static function ($query): void {
                $query->selectRaw('1')
                    ->from('inventory_lots')
                    ->whereColumn('inventory_lots.tenant_id', 'stock_levels.tenant_id')
                    ->whereColumn('inventory_lots.product_variant_id', 'stock_levels.product_variant_id')
                    ->whereColumn('inventory_lots.branch_id', 'stock_levels.branch_id');
            })
            ->select([
                'stock_levels.id',
                'stock_levels.tenant_id',
                'stock_levels.product_variant_id',
                'stock_levels.branch_id',
                'stock_levels.quantity_on_hand',
                'product_variants.cost_price',
            ])
            ->orderBy('stock_levels.id')
            ->chunkById(200, static function ($levels): void {
                $rows = [];

                foreach ($levels as $level) {
                    $rows[] = [
                        'tenant_id' => $level->tenant_id,
                        'product_variant_id' => $level->product_variant_id,
                        'branch_id' => $level->branch_id,
                        'quantity_remaining' => $level->quantity_on_hand,
                        'unit_cost' => $level->cost_price,
                        'received_at' => '2026-07-21 21:00:22',
                        'created_at' => '2026-07-21 21:00:22',
                    ];
                }

                if ($rows !== []) {
                    DB::table('inventory_lots')->insert($rows);
                }
            }, 'stock_levels.id', 'id');
    }

    public function down(): void
    {
        // Intentionally non-destructive: synthetic lots cannot be distinguished safely later.
    }
};
