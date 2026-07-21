<?php

namespace App\Infrastructure\Models;

use App\Infrastructure\Shared\HasTenantScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'branch_id', 'coa_id', 'date', 'debit_total', 'credit_total'])]
class DailyAccountBalance extends Model
{
    use HasTenantScope;

    /** @var array<string, mixed> */
    protected $attributes = ['debit_total' => 0, 'credit_total' => 0];

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<ChartOfAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'debit_total' => 'decimal:2',
            'credit_total' => 'decimal:2',
        ];
    }
}
