<?php

namespace App\Infrastructure\Models;

use App\Infrastructure\Shared\HasTenantScope;
use App\Infrastructure\Shared\IsImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'invoice_id', 'product_variant_id', 'tax_id', 'quantity', 'unit_price_at_sale', 'cost_price_at_sale', 'tax_rate_at_sale', 'line_total', 'cost_total_at_sale'])]
class InvoiceItem extends Model
{
    use HasTenantScope, IsImmutable;

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
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
            'unit_price_at_sale' => 'decimal:4',
            'cost_price_at_sale' => 'decimal:4',
            'tax_rate_at_sale' => 'decimal:4',
            'line_total' => 'decimal:2',
            'cost_total_at_sale' => 'decimal:2',
        ];
    }
}
