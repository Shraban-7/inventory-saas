<?php

namespace App\Presentation\Resources;

use App\Infrastructure\Models\StockTransfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockTransferResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $transfer = $this->resource;

        if (! $transfer instanceof StockTransfer) {
            return [];
        }

        return [
            'id' => $transfer->getKey(),
            'from_branch_id' => $transfer->from_branch_id,
            'to_branch_id' => $transfer->to_branch_id,
            'status' => $transfer->status,
            'transferred_at' => $transfer->transferred_at,
            'items' => $this->whenLoaded('items'),
        ];
    }
}
