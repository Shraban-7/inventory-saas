<?php

namespace App\Presentation\Resources;

use App\Infrastructure\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $order = $this->resource;
        if (! $order instanceof PurchaseOrder) {
            return [];
        }

        $expectedDate = $order->getRawOriginal('expected_date');

        return [
            'id' => $order->getKey(),
            'branch_id' => $order->branch_id,
            'supplier_id' => $order->supplier_id,
            'po_number' => $order->po_number,
            'status' => (string) $order->getRawOriginal('status'),
            'order_date' => (string) $order->getRawOriginal('order_date'),
            'expected_date' => is_string($expectedDate) ? $expectedDate : null,
            'notes' => $order->notes,
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'items' => PurchaseOrderItemResource::collection($this->whenLoaded('items')),
            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at,
        ];
    }
}
