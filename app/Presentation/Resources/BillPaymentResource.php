<?php

namespace App\Presentation\Resources;

use App\Infrastructure\Models\BillPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillPaymentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $payment = $this->resource;

        return $payment instanceof BillPayment ? [
            'id' => $payment->getKey(),
            'branch_id' => $payment->branch_id,
            'supplier_id' => $payment->supplier_id,
            'bill_id' => $payment->bill_id,
            'amount' => $payment->amount,
            'payment_method' => (string) $payment->getRawOriginal('payment_method'),
            'payment_date' => (string) $payment->getRawOriginal('payment_date'),
            'reference' => $payment->reference,
            'created_at' => $payment->created_at,
        ] : [];
    }
}
