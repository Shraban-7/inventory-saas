<?php

namespace App\Presentation\Requests;

use App\Application\DTOs\InvoiceData;
use App\Application\DTOs\InvoiceItemData;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LogicException;

class StoreInvoiceRequest extends FormRequest
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
            'customer_id' => ['required', 'integer', $tenantExists('customers')->whereNull('deleted_at')],
            'invoice_date' => ['required', 'date_format:Y-m-d'],
            'due_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:invoice_date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'integer', 'distinct', $tenantExists('product_variants')->whereNull('deleted_at')],
            'items.*.quantity' => ['required', 'numeric', 'decimal:0,4', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'decimal:0,4', 'gte:0'],
            'items.*.tax_id' => ['nullable', 'integer', $tenantExists('taxes')],
        ];
    }

    public function invoiceData(): InvoiceData
    {
        $data = $this->validated();
        $validatedItems = $data['items'] ?? null;

        if (! is_array($validatedItems)) {
            throw new LogicException('Validated invoice items must be an array.');
        }

        $items = [];

        foreach ($validatedItems as $item) {
            if (! is_array($item)) {
                throw new LogicException('Each validated invoice item must be an array.');
            }

            $items[] = new InvoiceItemData(
                (int) $item['variant_id'],
                (string) $item['quantity'],
                (string) $item['unit_price'],
                isset($item['tax_id']) ? (int) $item['tax_id'] : null,
            );
        }

        return new InvoiceData(
            (int) $data['branch_id'],
            (int) $data['customer_id'],
            new DateTimeImmutable((string) $data['invoice_date']),
            isset($data['due_date']) ? new DateTimeImmutable((string) $data['due_date']) : null,
            isset($data['notes']) ? (string) $data['notes'] : null,
            $items,
        );
    }
}
