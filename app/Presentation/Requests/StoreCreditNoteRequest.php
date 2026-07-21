<?php

namespace App\Presentation\Requests;

use App\Application\DTOs\CreditNoteData;
use App\Application\DTOs\CreditNoteItemData;
use App\Infrastructure\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use LogicException;

class StoreCreditNoteRequest extends FormRequest
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
            'invoice_id' => ['nullable', 'integer', $tenantExists('invoices')],
            'reason' => ['required', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'integer', 'distinct', $tenantExists('product_variants')->whereNull('deleted_at')],
            'items.*.quantity' => ['required', 'numeric', 'decimal:0,4', 'gt:0'],
            'items.*.unit_price' => [
                Rule::requiredIf(fn (): bool => $this->input('invoice_id') === null),
                'nullable',
                'numeric',
                'decimal:0,4',
                'gte:0',
            ],
            'items.*.tax_id' => ['nullable', 'integer', $tenantExists('taxes')],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $invoiceId = $this->input('invoice_id');

                if (! is_numeric($invoiceId)) {
                    return;
                }

                $invoice = Invoice::query()->with('items:id,invoice_id,product_variant_id')->find((int) $invoiceId);

                if (! $invoice instanceof Invoice) {
                    return;
                }

                if ((int) $this->input('branch_id') !== $invoice->branch_id) {
                    $validator->errors()->add('branch_id', 'The branch must match the linked invoice.');
                }
                if ((int) $this->input('customer_id') !== $invoice->customer_id) {
                    $validator->errors()->add('customer_id', 'The customer must match the linked invoice.');
                }

                $items = $this->input('items');
                if (! is_array($items)) {
                    return;
                }

                $soldVariantIds = $invoice->items->pluck('product_variant_id')->map(static fn ($id): int => (int) $id)->all();

                foreach ($items as $index => $item) {
                    if (is_array($item) && isset($item['variant_id']) && ! in_array((int) $item['variant_id'], $soldVariantIds, true)) {
                        $validator->errors()->add("items.{$index}.variant_id", 'The variant must exist on the linked invoice.');
                    }
                }
            },
        ];
    }

    public function creditNoteData(): CreditNoteData
    {
        $data = $this->validated();
        $validatedItems = $data['items'] ?? null;

        if (! is_array($validatedItems)) {
            throw new LogicException('Validated credit note items must be an array.');
        }

        $items = [];

        foreach ($validatedItems as $item) {
            if (! is_array($item)) {
                throw new LogicException('Each validated credit note item must be an array.');
            }

            $items[] = new CreditNoteItemData(
                (int) $item['variant_id'],
                (string) $item['quantity'],
                isset($item['unit_price']) ? (string) $item['unit_price'] : null,
                isset($item['tax_id']) ? (int) $item['tax_id'] : null,
            );
        }

        return new CreditNoteData(
            (int) $data['branch_id'],
            (int) $data['customer_id'],
            isset($data['invoice_id']) ? (int) $data['invoice_id'] : null,
            (string) $data['reason'],
            $items,
        );
    }
}
