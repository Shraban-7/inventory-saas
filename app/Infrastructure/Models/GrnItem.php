<?php

namespace App\Infrastructure\Models;

use App\Infrastructure\Shared\HasTenantScope;
use App\Infrastructure\Shared\IsImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'grn_id', 'product_variant_id', 'quantity_received', 'unit_cost'])]
class GrnItem extends Model
{
    use HasTenantScope, IsImmutable;

    /** @return BelongsTo<GoodsReceiptNote, $this> */
    public function goodsReceiptNote(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptNote::class, 'grn_id');
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity_received' => 'decimal:4',
            'unit_cost' => 'decimal:4',
        ];
    }
}
