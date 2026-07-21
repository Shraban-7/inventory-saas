<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Entities\CreditNoteItemRecord;
use App\Domain\Entities\CreditNoteRecord;
use App\Domain\Entities\InvoiceItemRecord;
use App\Domain\Entities\InvoiceRecord;
use App\Domain\Repositories\SalesRepository;
use App\Infrastructure\Models\CreditNote;
use App\Infrastructure\Models\Invoice;

final class EloquentSalesRepository implements SalesRepository
{
    /** @param list<InvoiceItemRecord> $items */
    public function createInvoice(InvoiceRecord $invoice, array $items): int
    {
        $model = Invoice::query()->create([
            'branch_id' => $invoice->branchId,
            'customer_id' => $invoice->customerId,
            'invoice_number' => $invoice->number,
            'invoice_date' => $invoice->invoiceDate,
            'due_date' => $invoice->dueDate,
            'total_amount' => $invoice->totalAmount,
            'tax_amount' => $invoice->taxAmount,
            'balance_due' => $invoice->balanceDue,
            'notes' => $invoice->notes,
        ]);

        $model->items()->createMany(array_map(
            static fn (InvoiceItemRecord $item): array => [
                'product_variant_id' => $item->variantId,
                'tax_id' => $item->taxId,
                'quantity' => $item->quantity,
                'unit_price_at_sale' => $item->unitPrice,
                'cost_price_at_sale' => $item->costPrice,
                'tax_rate_at_sale' => $item->taxRate,
                'line_total' => $item->lineTotal,
            ],
            $items,
        ));

        return $model->getKey();
    }

    /** @param list<CreditNoteItemRecord> $items */
    public function createCreditNoteDraft(CreditNoteRecord $creditNote, array $items): int
    {
        $model = CreditNote::query()->create([
            'branch_id' => $creditNote->branchId,
            'customer_id' => $creditNote->customerId,
            'invoice_id' => $creditNote->invoiceId,
            'reason' => $creditNote->reason,
            'total_amount' => $creditNote->totalAmount,
        ]);

        $model->items()->createMany(array_map(
            static fn (CreditNoteItemRecord $item): array => [
                'product_variant_id' => $item->variantId,
                'tax_id' => $item->taxId,
                'quantity' => $item->quantity,
                'unit_price' => $item->unitPrice,
                'cost_price_at_return' => $item->costPrice,
                'tax_rate_at_return' => $item->taxRate,
                'line_total' => $item->lineTotal,
            ],
            $items,
        ));

        return $model->getKey();
    }
}
