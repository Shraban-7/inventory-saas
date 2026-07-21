<?php

namespace App\Infrastructure\Models;

use App\Domain\Entities\ReportJobStatus;
use App\Domain\Entities\ReportJobType;
use App\Infrastructure\Shared\HasTenantScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'requested_by_user_id', 'requested_branch_id', 'type', 'status', 'parameters', 'parameter_hash', 'result', 'error_code', 'error_message', 'queued_at', 'started_at', 'completed_at', 'expires_at'])]
class ReportJob extends Model
{
    use HasTenantScope, HasUuids;

    /** @var array<string, mixed> */
    protected $attributes = ['status' => ReportJobStatus::Queued];

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

    /** @return BelongsTo<Branch, $this> */
    public function requestedBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'requested_branch_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => ReportJobType::class,
            'status' => ReportJobStatus::class,
            'parameters' => 'array',
            'result' => 'array',
            'queued_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
