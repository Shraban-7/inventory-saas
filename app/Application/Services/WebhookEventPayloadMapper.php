<?php

namespace App\Application\Services;

use App\Domain\Entities\WebhookEvent;
use App\Domain\Events\CreditNoteApproved;
use App\Domain\Events\GoodsReceived;
use App\Domain\Events\InvoiceCreated;
use App\Domain\Events\InvoiceVoided;
use App\Domain\Events\StockLow;
use App\Infrastructure\Models\CreditNote;
use App\Infrastructure\Models\GoodsReceiptNote;
use App\Infrastructure\Models\Invoice;

class WebhookEventPayloadMapper
{
    public function eventName(
        InvoiceCreated|InvoiceVoided|GoodsReceived|CreditNoteApproved|StockLow $event,
    ): WebhookEvent {
        return match ($event::class) {
            InvoiceCreated::class => WebhookEvent::InvoiceCreated,
            InvoiceVoided::class => WebhookEvent::InvoiceVoided,
            GoodsReceived::class => WebhookEvent::GoodsReceived,
            CreditNoteApproved::class => WebhookEvent::CreditNoteApproved,
            StockLow::class => WebhookEvent::StockLow,
        };
    }

    public function subjectId(
        InvoiceCreated|InvoiceVoided|GoodsReceived|CreditNoteApproved|StockLow $event,
    ): int {
        return match ($event::class) {
            InvoiceCreated::class, InvoiceVoided::class => $event->invoiceId,
            GoodsReceived::class => $event->goodsReceiptId,
            CreditNoteApproved::class => $event->creditNoteId,
            StockLow::class => $event->stockMovementId,
        };
    }

    /** @return array<string, mixed> */
    public function map(
        InvoiceCreated|InvoiceVoided|GoodsReceived|CreditNoteApproved|StockLow $event,
        string $occurrenceId,
    ): array {
        $eventName = $this->eventName($event);

        return [
            'id' => $occurrenceId,
            'event' => $eventName->value,
            'data' => match (true) {
                $event instanceof InvoiceCreated => $this->invoice($event->invoiceId),
                $event instanceof InvoiceVoided => $this->invoice($event->invoiceId),
                $event instanceof GoodsReceived => $this->goodsReceipt($event->goodsReceiptId),
                $event instanceof CreditNoteApproved => $this->creditNote($event->creditNoteId),
                $event instanceof StockLow => $this->stockLow($event),
            },
        ];
    }

    /** @return array<string, mixed> */
    private function invoice(int $id): array
    {
        $invoice = Invoice::query()->findOrFail($id);

        return [
            'id' => $invoice->getKey(),
            'branch_id' => $invoice->branch_id,
            'customer_id' => $invoice->customer_id,
            'invoice_number' => $invoice->invoice_number,
            'status' => (string) $invoice->getRawOriginal('status'),
            'total_amount' => $invoice->total_amount,
            'balance_due' => $invoice->balance_due,
            'updated_at' => $invoice->updated_at?->toISOString(),
        ];
    }

    /** @return array<string, mixed> */
    private function goodsReceipt(int $id): array
    {
        $receipt = GoodsReceiptNote::query()->findOrFail($id);

        return [
            'id' => $receipt->getKey(),
            'branch_id' => $receipt->branch_id,
            'purchase_order_id' => $receipt->purchase_order_id,
            'supplier_id' => $receipt->supplier_id,
            'grn_number' => $receipt->grn_number,
            'received_at' => $receipt->getRawOriginal('received_at'),
        ];
    }

    /** @return array<string, mixed> */
    private function creditNote(int $id): array
    {
        $creditNote = CreditNote::query()->findOrFail($id);

        return [
            'id' => $creditNote->getKey(),
            'branch_id' => $creditNote->branch_id,
            'customer_id' => $creditNote->customer_id,
            'invoice_id' => $creditNote->invoice_id,
            'status' => (string) $creditNote->getRawOriginal('status'),
            'total_amount' => $creditNote->total_amount,
            'updated_at' => $creditNote->updated_at?->toISOString(),
        ];
    }

    /** @return array<string, int|string> */
    private function stockLow(StockLow $event): array
    {
        return [
            'variant_id' => $event->variantId,
            'branch_id' => $event->branchId,
            'quantity_on_hand' => $event->quantityOnHand,
            'reorder_point' => $event->reorderPoint,
            'stock_movement_id' => $event->stockMovementId,
        ];
    }
}
