<?php

namespace App\Infrastructure\Models;

use App\Infrastructure\Shared\HasTenantScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $payload_hash
 * @property array{content: string, content_type?: string} $response_body
 * @property int $response_status
 * @property CarbonImmutable $expires_at
 */
#[Fillable([
    'tenant_id',
    'key',
    'payload_hash',
    'response_body',
    'response_status',
    'expires_at',
])]
class IdempotencyRequest extends Model
{
    use HasTenantScope;

    public const UPDATED_AT = null;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'response_body' => 'array',
            'response_status' => 'integer',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
