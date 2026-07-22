<?php

namespace App\Presentation\Resources;

use App\Domain\Entities\WebhookEvent;
use App\Infrastructure\Models\WebhookEndpoint;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class WebhookEndpointResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $endpoint = $this->resource;

        if (! $endpoint instanceof WebhookEndpoint) {
            return [];
        }

        $events = $endpoint->getAttribute('events');

        return [
            'id' => $endpoint->getKey(),
            'url' => $endpoint->url,
            'events' => $events instanceof Collection
                ? $events
                    ->map(static fn (WebhookEvent $event): string => $event->value)
                    ->values()
                    ->all()
                : [],
            'is_active' => $endpoint->is_active,
            'deactivated_at' => $endpoint->deactivated_at,
            'created_at' => $endpoint->created_at,
            'updated_at' => $endpoint->updated_at,
        ];
    }
}
