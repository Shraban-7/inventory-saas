<?php

namespace App\Infrastructure\Models;

use App\Domain\Entities\WebhookEvent;
use App\Infrastructure\Shared\HasTenantScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable(['tenant_id', 'url', 'secret', 'events', 'is_active', 'deactivated_at'])]
#[Hidden(['secret'])]
class WebhookEndpoint extends Model
{
    use HasTenantScope, HasUuids;

    /** @var array<string, mixed> */
    protected $attributes = ['is_active' => true];

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return HasMany<WebhookDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function subscribesTo(WebhookEvent $event): bool
    {
        $events = $this->getAttribute('events');

        return $events instanceof Collection && $events->contains($event);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'events' => AsEnumCollection::of(WebhookEvent::class),
            'is_active' => 'boolean',
            'deactivated_at' => 'immutable_datetime',
        ];
    }
}
