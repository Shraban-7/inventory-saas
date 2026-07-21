<?php

namespace App\Presentation\Requests;

use App\Application\DTOs\BillPaymentData;
use App\Domain\Entities\PurchasePaymentMethod;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBillPaymentRequest extends FormRequest
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
            'payment_method' => ['required', Rule::enum(PurchasePaymentMethod::class)],
            'payment_date' => ['required', 'date_format:Y-m-d'],
            'reference' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function billPaymentData(): BillPaymentData
    {
        $data = $this->validated();

        return new BillPaymentData(
            (string) $data['amount'],
            PurchasePaymentMethod::from((string) $data['payment_method']),
            new DateTimeImmutable((string) $data['payment_date']),
            isset($data['reference']) ? (string) $data['reference'] : null,
        );
    }
}
