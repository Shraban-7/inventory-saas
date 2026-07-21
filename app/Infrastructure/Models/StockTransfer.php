<?php

namespace App\Infrastructure\Models;

use App\Domain\Entities\StockTransferStatus;
use App\Infrastructure\Shared\HasTenantScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'from_branch_id', 'to_branch_id', 'idempotency_key', 'payload_hash', 'status', 'transferred_at'])]
class StockTransfer extends Model
{
    use HasTenantScope;

    /** @return BelongsTo<Branch, $this> */
    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    /** @return BelongsTo<Branch, $this> */
    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    /** @return HasMany<StockTransferItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => StockTransferStatus::class, 'transferred_at' => 'immutable_datetime'];
    }
}
