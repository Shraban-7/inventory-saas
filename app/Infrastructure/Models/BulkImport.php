<?php

namespace App\Infrastructure\Models;

use App\Domain\Entities\BulkImportStatus;
use App\Domain\Entities\BulkImportType;
use App\Infrastructure\Shared\HasTenantScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'requested_by_user_id', 'type', 'status', 'disk', 'path', 'batch_id', 'total_rows', 'processed_rows', 'succeeded_rows', 'failed_rows', 'skipped_rows', 'error_code', 'started_at', 'completed_at'])]
class BulkImport extends Model
{
    use HasTenantScope, HasUuids;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => BulkImportStatus::Queued,
        'total_rows' => 0,
        'processed_rows' => 0,
        'succeeded_rows' => 0,
        'failed_rows' => 0,
        'skipped_rows' => 0,
    ];

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /** @return HasMany<BulkImportRow, $this> */
    public function rows(): HasMany
    {
        return $this->hasMany(BulkImportRow::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => BulkImportType::class,
            'status' => BulkImportStatus::class,
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'succeeded_rows' => 'integer',
            'failed_rows' => 'integer',
            'skipped_rows' => 'integer',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
