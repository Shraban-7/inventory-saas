<?php

namespace App\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:255', Rule::unique('product_variants')->where('tenant_id', current_tenant_id())],
            'barcode' => ['nullable', 'string', 'max:255'],
            'cost_price' => ['required', 'numeric', 'decimal:0,4', 'gte:0'],
            'sale_price' => ['required', 'numeric', 'decimal:0,4', 'gte:0'],
            'reorder_point' => ['sometimes', 'integer', 'min:0'],
            'attribute_value_ids' => ['sometimes', 'array'],
            'attribute_value_ids.*' => ['integer', 'distinct', Rule::exists('attribute_values', 'id')->where('tenant_id', current_tenant_id())],
        ];
    }

    /** @return array{sku: string, barcode?: string|null, cost_price: string, sale_price: string, reorder_point?: int, attribute_value_ids?: list<int>} */
    public function variantData(): array
    {
        $data = $this->validated();
        $result = [
            'sku' => (string) $data['sku'],
            'cost_price' => (string) $data['cost_price'],
            'sale_price' => (string) $data['sale_price'],
        ];

        if (array_key_exists('barcode', $data)) {
            $result['barcode'] = $data['barcode'] === null ? null : (string) $data['barcode'];
        }
        if (isset($data['reorder_point'])) {
            $result['reorder_point'] = (int) $data['reorder_point'];
        }
        if (isset($data['attribute_value_ids']) && is_array($data['attribute_value_ids'])) {
            $result['attribute_value_ids'] = array_values(array_map('intval', $data['attribute_value_ids']));
        }

        return $result;
    }
}
