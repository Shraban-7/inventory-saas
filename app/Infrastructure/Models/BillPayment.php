<?php

namespace App\Infrastructure\Models;

use App\Domain\Entities\PurchasePaymentMethod;
use App\Infrastructure\Shared\HasTenantScope;
use App\Infrastructure\Shared\IsImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['tenant_id', 'branch_id', 'supplier_id', 'bill_id', 'amount', 'payment_method', 'payment_date', 'reference'])]
class BillPayment extends Model
{
    use HasTenantScope, IsImmutable;

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

    /** @return MorphMany<JournalEntry, $this> */
    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'reference');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_method' => PurchasePaymentMethod::class,
            'payment_date' => 'immutable_date',
        ];
    }
}
