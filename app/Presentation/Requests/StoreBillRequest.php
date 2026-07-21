<?php

namespace App\Presentation\Requests;

use App\Application\DTOs\BillData;
use App\Application\DTOs\BillItemData;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LogicException;

class StoreBillRequest extends FormRequest
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
            'grn_id' => ['nullable', 'integer', $tenantExists('goods_receipt_notes')],
            'bill_number' => ['required', 'string', 'max:255'],
            'bill_date' => ['required', 'date_format:Y-m-d'],
            'due_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:bill_date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'integer', 'distinct', $tenantExists('product_variants')->whereNull('deleted_at')],
            'items.*.tax_id' => ['nullable', 'integer', $tenantExists('taxes')],
            'items.*.quantity' => ['required', 'numeric', 'decimal:0,4', 'gt:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'decimal:0,4', 'gt:0'],
        ];
    }

    public function billData(): BillData
    {
        $data = $this->validated();
        $validatedItems = $data['items'] ?? null;

        if (! is_array($validatedItems)) {
            throw new LogicException('Validated bill items must be an array.');
        }

        $items = [];
        foreach ($validatedItems as $item) {
            if (! is_array($item)) {
                throw new LogicException('Each validated bill item must be an array.');
            }
            $items[] = new BillItemData(
                (int) $item['variant_id'],
                isset($item['tax_id']) ? (int) $item['tax_id'] : null,
                (string) $item['quantity'],
                (string) $item['unit_cost'],
            );
        }

        return new BillData(
            (int) $data['branch_id'],
            (int) $data['supplier_id'],
            isset($data['grn_id']) ? (int) $data['grn_id'] : null,
            (string) $data['bill_number'],
            new DateTimeImmutable((string) $data['bill_date']),
            isset($data['due_date']) ? new DateTimeImmutable((string) $data['due_date']) : null,
            $items,
        );
    }
}
