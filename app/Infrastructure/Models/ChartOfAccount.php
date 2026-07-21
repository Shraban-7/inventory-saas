<?php

namespace App\Infrastructure\Models;

use App\Domain\Entities\ChartOfAccountType;
use App\Infrastructure\Shared\HasTenantScope;
use Database\Factories\ChartOfAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'parent_id', 'code', 'name', 'type', 'is_system'])]
class ChartOfAccount extends Model
{
    /** @use HasFactory<ChartOfAccountFactory> */
    use HasFactory, HasTenantScope;

    /** @var array<string, mixed> */
    protected $attributes = ['is_system' => false];

    protected static function newFactory(): ChartOfAccountFactory
    {
        return ChartOfAccountFactory::new();
    }

    /** @return BelongsTo<ChartOfAccount, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<ChartOfAccount, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<JournalEntryLine, $this> */
    public function journalEntryLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'coa_id');
    }

    /** @return HasMany<Tax, $this> */
    public function taxes(): HasMany
    {
        return $this->hasMany(Tax::class, 'coa_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => ChartOfAccountType::class,
            'is_system' => 'boolean',
        ];
    }
}
