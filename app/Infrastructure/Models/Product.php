<?php

namespace App\Infrastructure\Models;

use App\Domain\Entities\CostingMethod;
use App\Infrastructure\Shared\HasTenantScope;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['tenant_id', 'category_id', 'name', 'description', 'costing_method'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasTenantScope, SoftDeletes;

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasMany<ProductVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['costing_method' => CostingMethod::class];
    }
}
