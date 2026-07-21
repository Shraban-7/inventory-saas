<?php

namespace App\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $branchExists = fn () => Rule::exists('branches', 'id')->where('tenant_id', current_tenant_id());

        return [
            'from_branch_id' => ['required', 'integer', $branchExists()],
            'to_branch_id' => ['required', 'integer', 'different:from_branch_id', $branchExists()],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'integer', 'distinct', Rule::exists('product_variants', 'id')->where('tenant_id', current_tenant_id())],
            'items.*.quantity' => ['required', 'numeric', 'decimal:0,4', 'gt:0'],
        ];
    }

    /** @return list<array{variant_id: int, quantity: string}> */
    public function items(): array
    {
        $validatedItems = $this->validated('items', []);
        $items = [];

        if (! is_array($validatedItems)) {
            return $items;
        }

        foreach ($validatedItems as $item) {
            if (is_array($item) && isset($item['variant_id'], $item['quantity'])) {
                $items[] = [
                    'variant_id' => (int) $item['variant_id'],
                    'quantity' => (string) $item['quantity'],
                ];
            }
        }

        return $items;
    }
}
