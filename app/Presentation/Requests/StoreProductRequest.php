<?php

namespace App\Presentation\Requests;

use App\Application\DTOs\ProductData;
use App\Domain\Entities\CostingMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('tenant_id', current_tenant_id())],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'costing_method' => ['sometimes', new Enum(CostingMethod::class)],
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.sku' => ['required', 'string', 'max:255', 'distinct'],
            'variants.*.barcode' => ['nullable', 'string', 'max:255'],
            'variants.*.cost_price' => ['required', 'numeric', 'decimal:0,4', 'gte:0'],
            'variants.*.sale_price' => ['required', 'numeric', 'decimal:0,4', 'gte:0'],
            'variants.*.reorder_point' => ['sometimes', 'integer', 'min:0'],
            'variants.*.attribute_value_ids' => ['sometimes', 'array'],
            'variants.*.attribute_value_ids.*' => ['integer', 'distinct', Rule::exists('attribute_values', 'id')->where('tenant_id', current_tenant_id())],
        ];
    }

    public function productData(): ProductData
    {
        $data = $this->validated();
        $variants = [];
        $validatedVariants = $data['variants'] ?? [];

        if (! is_array($validatedVariants)) {
            $validatedVariants = [];
        }

        foreach ($validatedVariants as $variant) {
            if (! is_array($variant)) {
                continue;
            }

            $normalized = [
                'sku' => (string) $variant['sku'],
                'cost_price' => (string) $variant['cost_price'],
                'sale_price' => (string) $variant['sale_price'],
            ];
            if (array_key_exists('barcode', $variant)) {
                $normalized['barcode'] = $variant['barcode'] === null ? null : (string) $variant['barcode'];
            }
            if (isset($variant['reorder_point'])) {
                $normalized['reorder_point'] = (int) $variant['reorder_point'];
            }
            if (isset($variant['attribute_value_ids']) && is_array($variant['attribute_value_ids'])) {
                $normalized['attribute_value_ids'] = array_values(array_map('intval', $variant['attribute_value_ids']));
            }
            $variants[] = $normalized;
        }

        return new ProductData(
            (int) $data['category_id'],
            (string) $data['name'],
            isset($data['description']) ? (string) $data['description'] : null,
            CostingMethod::from((string) ($data['costing_method'] ?? 'fifo')),
            $variants,
        );
    }
}
