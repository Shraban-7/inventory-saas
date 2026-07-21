<?php

namespace App\Presentation\Requests;

use App\Application\DTOs\ReceiptData;
use App\Domain\Entities\PaymentMethod;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use LogicException;

class StoreReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
            'payment_method' => ['required', new Enum(PaymentMethod::class)],
            'payment_date' => ['required', 'date_format:Y-m-d'],
            'reference' => ['nullable', 'string', 'min:1', 'max:255'],
        ];
    }

    public function receiptData(): ReceiptData
    {
        $data = $this->validated();
        $invoiceId = $this->route('invoiceId');

        if (! is_numeric($invoiceId)) {
            throw new LogicException('The validated receipt route must contain an invoice identifier.');
        }

        return new ReceiptData(
            (int) $invoiceId,
            (string) $data['amount'],
            PaymentMethod::from((string) $data['payment_method']),
            new DateTimeImmutable((string) $data['payment_date']),
            isset($data['reference']) ? (string) $data['reference'] : null,
        );
    }
}
