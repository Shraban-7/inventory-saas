<?php

namespace App\Presentation\Resources;

use App\Infrastructure\Models\Receipt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiptResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $receipt = $this->resource;

        if (! $receipt instanceof Receipt) {
            return [];
        }

        return [
            'id' => $receipt->getKey(),
            'branch_id' => $receipt->branch_id,
            'customer_id' => $receipt->customer_id,
            'invoice_id' => $receipt->invoice_id,
            'amount' => $receipt->amount,
            'payment_method' => (string) $receipt->getRawOriginal('payment_method'),
            'payment_date' => (string) $receipt->getRawOriginal('payment_date'),
            'reference' => $receipt->reference,
            'created_at' => $receipt->created_at,
        ];
    }
}
