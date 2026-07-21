<?php

namespace App\Infrastructure\Models;

use App\Infrastructure\Shared\HasTenantScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'zone_id', 'code'])]
class BinLocation extends Model
{
    use HasTenantScope;

    /** @return BelongsTo<WarehouseZone, $this> */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(WarehouseZone::class, 'zone_id');
    }
}
