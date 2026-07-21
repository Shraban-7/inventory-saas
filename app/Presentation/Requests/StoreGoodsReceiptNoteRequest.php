<?php

namespace App\Presentation\Requests;

use App\Application\DTOs\GoodsReceiptData;
use App\Application\DTOs\GrnItemData;
use App\Application\Services\CanonicalJson;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LogicException;

class StoreGoodsReceiptNoteRequest extends FormRequest
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
            'purchase_order_id' => ['nullable', 'integer', $tenantExists('purchase_orders')],
            'received_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'integer', 'distinct', $tenantExists('product_variants')->whereNull('deleted_at')],
            'items.*.quantity' => ['required', 'numeric', 'decimal:0,4', 'gt:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'decimal:0,4', 'gt:0'],
        ];
    }

    public function goodsReceiptData(CanonicalJson $canonicalJson): GoodsReceiptData
    {
        $data = $this->validated();
        $validatedItems = $data['items'] ?? null;
        $key = $this->header('Idempotency-Key');

        if (! is_array($validatedItems) || ! is_string($key)) {
            throw new LogicException('Validated goods receipt shape or idempotency key is invalid.');
        }

        $items = [];
        foreach ($validatedItems as $item) {
            if (! is_array($item)) {
                throw new LogicException('Each validated goods receipt item must be an array.');
            }
            $items[] = new GrnItemData((int) $item['variant_id'], (string) $item['quantity'], (string) $item['unit_cost']);
        }

        return new GoodsReceiptData(
            (int) $data['branch_id'],
            (int) $data['supplier_id'],
            isset($data['purchase_order_id']) ? (int) $data['purchase_order_id'] : null,
            new DateTimeImmutable((string) $data['received_at']),
            isset($data['notes']) ? (string) $data['notes'] : null,
            $key,
            hash('sha256', $canonicalJson->encode($data)),
            $items,
        );
    }
}
