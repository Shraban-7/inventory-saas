<?php

namespace App\Presentation\Resources;

use App\Infrastructure\Models\GoodsReceiptNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoodsReceiptNoteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $grn = $this->resource;
        if (! $grn instanceof GoodsReceiptNote) {
            return [];
        }

        return [
            'id' => $grn->getKey(),
            'branch_id' => $grn->branch_id,
            'supplier_id' => $grn->supplier_id,
            'purchase_order_id' => $grn->purchase_order_id,
            'grn_number' => $grn->grn_number,
            'received_at' => (string) $grn->getRawOriginal('received_at'),
            'notes' => $grn->notes,
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'purchase_order' => new PurchaseOrderResource($this->whenLoaded('purchaseOrder')),
            'items' => GrnItemResource::collection($this->whenLoaded('items')),
            'created_at' => $grn->created_at,
        ];
    }
}
