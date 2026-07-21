<?php

namespace App\Infrastructure\Models;

use App\Domain\Entities\BillStatus;
use App\Infrastructure\Shared\HasTenantScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['tenant_id', 'branch_id', 'supplier_id', 'grn_id', 'bill_number', 'bill_date', 'due_date', 'status', 'total_amount', 'tax_amount', 'balance_due'])]
class Bill extends Model
{
    use HasTenantScope;

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'draft'];

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

    /** @return BelongsTo<GoodsReceiptNote, $this> */
    public function goodsReceiptNote(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptNote::class, 'grn_id');
    }

    /** @return HasMany<BillItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(BillItem::class);
    }

    /** @return HasMany<BillPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(BillPayment::class);
    }

    /** @return HasMany<SupplierReturn, $this> */
    public function supplierReturns(): HasMany
    {
        return $this->hasMany(SupplierReturn::class);
    }

    /** @return MorphMany<JournalEntry, $this> */
    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'reference');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'bill_date' => 'immutable_date',
            'due_date' => 'immutable_date',
            'status' => BillStatus::class,
            'total_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'balance_due' => 'decimal:2',
        ];
    }
}
