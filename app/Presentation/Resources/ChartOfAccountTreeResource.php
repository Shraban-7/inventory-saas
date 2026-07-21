<?php

namespace App\Presentation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChartOfAccountTreeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $account = $this->resource;

        if (! is_array($account)) {
            return [];
        }

        return [
            'id' => $account['id'] ?? null,
            'parent_id' => $account['parent_id'] ?? null,
            'code' => $account['code'] ?? null,
            'name' => $account['name'] ?? null,
            'type' => $account['type'] ?? null,
            'is_system' => $account['is_system'] ?? false,
            'children' => $account['children'] ?? [],
        ];
    }
}
