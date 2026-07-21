<?php

namespace App\Infrastructure\Models;

use App\Infrastructure\Shared\HasTenantScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'product_variant_id', 'branch_id', 'quantity_on_hand'])]
class StockLevel extends Model
{
    use HasTenantScope;

    public $timestamps = false;

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['quantity_on_hand' => 'decimal:4'];
    }
}
