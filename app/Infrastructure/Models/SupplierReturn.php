<?php

namespace App\Infrastructure\Models;

use App\Domain\Entities\SupplierReturnStatus;
use App\Infrastructure\Shared\HasTenantScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['tenant_id', 'branch_id', 'supplier_id', 'bill_id', 'idempotency_key', 'payload_hash', 'reason', 'status', 'total_cost'])]
class SupplierReturn extends Model
{
    use HasTenantScope;

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'draft', 'total_cost' => 0];

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<Bill, $this> */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    /** @return HasMany<SupplierReturnItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SupplierReturnItem::class);
    }

    /** @return MorphMany<JournalEntry, $this> */
    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'reference');
    }

    /** @return MorphMany<StockMovement, $this> */
    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'source');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => SupplierReturnStatus::class,
            'total_cost' => 'decimal:2',
        ];
    }
}
