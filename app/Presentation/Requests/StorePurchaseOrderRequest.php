<?php

namespace App\Presentation\Requests;

use App\Application\DTOs\PurchaseOrderData;
use App\Application\DTOs\PurchaseOrderItemData;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LogicException;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantExists = static fn (string $table) => Rule::exists($table, 'id')->where('tenant_id', current_tenant_id());

        return [
            'branch_id' => ['required', 'integer', $tenantExists('branches')],
            'supplier_id' => ['required', 'integer', $tenantExists('suppliers')->whereNull('deleted_at')],
            'order_date' => ['required', 'date_format:Y-m-d'],
            'expected_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:order_date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'integer', 'distinct', $tenantExists('product_variants')->whereNull('deleted_at')],
            'items.*.quantity' => ['required', 'numeric', 'decimal:0,4', 'gt:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'decimal:0,4', 'gt:0'],
        ];
    }

    public function purchaseOrderData(): PurchaseOrderData
    {
        $data = $this->validated();
        $validatedItems = $data['items'] ?? null;

        if (! is_array($validatedItems)) {
            throw new LogicException('Validated purchase order items must be an array.');
        }

        $items = [];
        foreach ($validatedItems as $item) {
            if (! is_array($item)) {
                throw new LogicException('Each validated purchase order item must be an array.');
            }
            $items[] = new PurchaseOrderItemData(
                (int) $item['variant_id'],
                (string) $item['quantity'],
                (string) $item['unit_cost'],
            );
        }

        return new PurchaseOrderData(
            (int) $data['branch_id'],
            (int) $data['supplier_id'],
            new DateTimeImmutable((string) $data['order_date']),
            isset($data['expected_date']) ? new DateTimeImmutable((string) $data['expected_date']) : null,
            isset($data['notes']) ? (string) $data['notes'] : null,
            $items,
        );
    }
}
