<?php

namespace App\Infrastructure\Models;

use App\Infrastructure\Shared\HasTenantScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'coa_id', 'name', 'rate', 'is_compound'])]
class Tax extends Model
{
    use HasTenantScope;

    /** @var array<string, mixed> */
    protected $attributes = ['is_compound' => false];

    /** @return BelongsTo<ChartOfAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_id');
    }

    /** @return HasMany<InvoiceItem, $this> */
    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /** @return HasMany<CreditNoteItem, $this> */
    public function creditNoteItems(): HasMany
    {
        return $this->hasMany(CreditNoteItem::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['rate' => 'decimal:4', 'is_compound' => 'boolean'];
    }
}
