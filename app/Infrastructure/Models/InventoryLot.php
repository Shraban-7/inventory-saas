<?php

namespace App\Infrastructure\Models;

use App\Infrastructure\Shared\HasTenantScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tenant_id', 'product_variant_id', 'branch_id', 'quantity_remaining', 'unit_cost', 'received_at'])]
class InventoryLot extends Model
{
    use HasTenantScope;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::addGlobalScope('fifo', fn (Builder $query) => $query->oldest('received_at'));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity_remaining' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'received_at' => 'immutable_datetime',
        ];
    }
}
