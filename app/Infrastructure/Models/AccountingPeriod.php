<?php

namespace App\Infrastructure\Models;

use App\Infrastructure\Shared\HasTenantScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'year', 'month', 'is_locked', 'locked_at', 'locked_by_user_id'])]
class AccountingPeriod extends Model
{
    use HasTenantScope;

    /** @var array<string, mixed> */
    protected $attributes = ['is_locked' => false];

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<User, $this> */
    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'is_locked' => 'boolean',
            'locked_at' => 'immutable_datetime',
        ];
    }
}
