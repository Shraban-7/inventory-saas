<?php

namespace App\Infrastructure\Models;

use App\Infrastructure\Shared\HasTenantScope;
use App\Infrastructure\Shared\IsImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'credit_note_id', 'product_variant_id', 'tax_id', 'quantity', 'unit_price', 'cost_price_at_return', 'tax_rate_at_return', 'line_total', 'cost_total_at_return'])]
class CreditNoteItem extends Model
{
    use HasTenantScope, IsImmutable;

    /** @return BelongsTo<CreditNote, $this> */
    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /** @return BelongsTo<Tax, $this> */
    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'cost_price_at_return' => 'decimal:4',
            'tax_rate_at_return' => 'decimal:4',
            'line_total' => 'decimal:2',
            'cost_total_at_return' => 'decimal:2',
        ];
    }
}
