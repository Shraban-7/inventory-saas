<?php

namespace App\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('tenant_id', current_tenant_id())],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'filter' => ['sometimes', 'array'],
            'filter.low_stock' => ['sometimes', 'boolean'],
            'filter.branch_id' => [
                'sometimes',
                'integer',
                Rule::exists('branches', 'id')->where('tenant_id', current_tenant_id()),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $filter = $this->input('filter');

        if (! is_array($filter) || ! array_key_exists('low_stock', $filter)) {
            return;
        }

        $parsed = filter_var($filter['low_stock'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($parsed === null) {
            return;
        }

        $this->merge([
            'filter' => [
                ...$filter,
                'low_stock' => $parsed,
            ],
        ]);
    }
}
