<?php

namespace App\Infrastructure\Models;

use App\Domain\Entities\BulkImportRowStatus;
use App\Infrastructure\Shared\HasTenantScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'bulk_import_id', 'row_number', 'target_key', 'status', 'error_code', 'error_message'])]
class BulkImportRow extends Model
{
    use HasTenantScope;

    /** @var array<string, mixed> */
    protected $attributes = ['status' => BulkImportRowStatus::Pending];

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<BulkImport, $this> */
    public function bulkImport(): BelongsTo
    {
        return $this->belongsTo(BulkImport::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'row_number' => 'integer',
            'status' => BulkImportRowStatus::class,
        ];
    }
}
