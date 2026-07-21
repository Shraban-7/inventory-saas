<?php

namespace App\Presentation\Resources;

use App\Infrastructure\Models\Bill;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $bill = $this->resource;
        if (! $bill instanceof Bill) {
            return [];
        }

        $dueDate = $bill->getRawOriginal('due_date');

        return [
            'id' => $bill->getKey(),
            'branch_id' => $bill->branch_id,
            'supplier_id' => $bill->supplier_id,
            'grn_id' => $bill->grn_id,
            'bill_number' => $bill->bill_number,
            'bill_date' => (string) $bill->getRawOriginal('bill_date'),
            'due_date' => is_string($dueDate) ? $dueDate : null,
            'status' => (string) $bill->getRawOriginal('status'),
            'total_amount' => $bill->total_amount,
            'tax_amount' => $bill->tax_amount,
            'balance_due' => $bill->balance_due,
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'goods_receipt_note' => new GoodsReceiptNoteResource($this->whenLoaded('goodsReceiptNote')),
            'items' => BillItemResource::collection($this->whenLoaded('items')),
            'payments' => BillPaymentResource::collection($this->whenLoaded('payments')),
            'created_at' => $bill->created_at,
            'updated_at' => $bill->updated_at,
        ];
    }
}
