<?php

namespace App\Presentation\Requests;

use App\Domain\Entities\BillStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListBillsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(BillStatus::class)],
            'supplier_id' => ['sometimes', 'integer', Rule::exists('suppliers', 'id')
                ->where('tenant_id', current_tenant_id())->whereNull('deleted_at')],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
