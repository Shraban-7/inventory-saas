<?php

namespace App\Infrastructure\Models;

use App\Domain\Entities\StockMovementType;
use App\Infrastructure\Shared\HasTenantScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tenant_id', 'product_variant_id', 'branch_id', 'idempotency_key', 'payload_hash', 'quantity_delta', 'type', 'reason'])]
class StockAdjustment extends Model
{
    use HasTenantScope;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity_delta' => 'decimal:4',
            'type' => StockMovementType::class,
        ];
    }
}
