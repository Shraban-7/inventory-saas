<?php

namespace App\Infrastructure\Models;

use App\Infrastructure\Shared\HasTenantScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'year', 'sequence'])]
class ManualJournalSequence extends Model
{
    use HasTenantScope;

    /** @var array<string, mixed> */
    protected $attributes = ['sequence' => 0];

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['year' => 'integer', 'sequence' => 'integer'];
    }
}
