<?php

namespace App\Infrastructure\Models;

use App\Infrastructure\Shared\HasTenantScope;
use App\Infrastructure\Shared\IsImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['tenant_id', 'user_id', 'action', 'entity_type', 'entity_id', 'old_values', 'new_values', 'ip_address', 'user_agent', 'session_id'])]
class AuditLog extends Model
{
    use HasTenantScope, IsImmutable;

    public const UPDATED_AT = null;

    /** @return MorphTo<Model, $this> */
    public function entity(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array'];
    }
}
