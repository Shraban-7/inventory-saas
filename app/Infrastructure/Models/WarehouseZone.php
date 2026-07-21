<?php

namespace App\Infrastructure\Models;

use App\Infrastructure\Shared\HasTenantScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'warehouse_id', 'name'])]
class WarehouseZone extends Model
{
    use HasTenantScope;

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return HasMany<BinLocation, $this> */
    public function binLocations(): HasMany
    {
        return $this->hasMany(BinLocation::class, 'zone_id');
    }
}
