<?php

namespace App\Infrastructure\Models;

use App\Domain\Entities\WebhookDeliveryStatus;
use App\Domain\Entities\WebhookEvent;
use App\Infrastructure\Shared\HasTenantScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'id',
    'occurrence_id',
    'tenant_id',
    'webhook_endpoint_id',
    'event',
    'payload',
    'status',
    'response_code',
    'attempts',
    'next_retry_at',
    'error_detail',
    'delivered_at',
])]
class WebhookDelivery extends Model
{
    use HasTenantScope, HasUuids;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'pending',
        'attempts' => 0,
    ];

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<WebhookEndpoint, $this> */
    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'event' => WebhookEvent::class,
            'status' => WebhookDeliveryStatus::class,
            'response_code' => 'integer',
            'attempts' => 'integer',
            'next_retry_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
        ];
    }
}
