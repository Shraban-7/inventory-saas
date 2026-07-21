<?php

namespace App\Infrastructure\Models;

use App\Domain\Entities\StockMovementType;
use App\Infrastructure\Shared\HasTenantScope;
use App\Infrastructure\Shared\IsImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['tenant_id', 'product_variant_id', 'branch_id', 'type', 'quantity_delta', 'unit_cost', 'source_type', 'source_id'])]
class StockMovement extends Model
{
    use HasTenantScope, IsImmutable;

    public const UPDATED_AT = null;

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

    /** @return MorphTo<Model, $this> */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity_delta' => 'decimal:4',
            'unit_cost' => 'decimal:4',
        ];
    }
}
