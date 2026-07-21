<?php

namespace App\Presentation\Resources;

use App\Infrastructure\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $invoice = $this->resource;

        if (! $invoice instanceof Invoice) {
            return [];
        }

        $dueDate = $invoice->getRawOriginal('due_date');

        return [
            'id' => $invoice->getKey(),
            'branch_id' => $invoice->branch_id,
            'customer_id' => $invoice->customer_id,
            'invoice_number' => $invoice->invoice_number,
            'invoice_date' => (string) $invoice->getRawOriginal('invoice_date'),
            'due_date' => is_string($dueDate) ? $dueDate : null,
            'status' => (string) $invoice->getRawOriginal('status'),
            'total_amount' => $invoice->total_amount,
            'tax_amount' => $invoice->tax_amount,
            'balance_due' => $invoice->balance_due,
            'notes' => $invoice->notes,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            'receipts' => ReceiptResource::collection($this->whenLoaded('receipts')),
            'created_at' => $invoice->created_at,
            'updated_at' => $invoice->updated_at,
        ];
    }
}
