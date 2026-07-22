<?php

namespace App\Infrastructure\Models;

use App\Domain\Entities\ArchiveDataset;
use App\Domain\Entities\ArchiveExportStatus;
use App\Infrastructure\Shared\HasTenantScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property ArchiveDataset $dataset
 * @property ArchiveExportStatus $status
 * @property int $period_year
 * @property string $schema_version
 * @property array<string, mixed>|null $manifest
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 */
#[Fillable([
    'tenant_id',
    'dataset',
    'period_year',
    'schema_version',
    'status',
    'disk',
    'path',
    'manifest',
    'checksum',
    'row_count',
    'error_code',
    'started_at',
    'completed_at',
])]
class ArchiveExport extends Model
{
    use HasTenantScope;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => ArchiveExportStatus::Pending,
    ];

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'dataset' => ArchiveDataset::class,
            'status' => ArchiveExportStatus::class,
            'period_year' => 'integer',
            'manifest' => 'array',
            'row_count' => 'integer',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
