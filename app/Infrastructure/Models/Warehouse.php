<?php

namespace App\Infrastructure\Models;

use App\Infrastructure\Shared\HasTenantScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'branch_id', 'name'])]
class Warehouse extends Model
{
    use HasTenantScope;

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return HasMany<WarehouseZone, $this> */
    public function zones(): HasMany
    {
        return $this->hasMany(WarehouseZone::class);
    }
}
