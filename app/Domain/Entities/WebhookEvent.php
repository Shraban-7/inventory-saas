<?php

namespace App\Domain\Entities;

enum WebhookEvent: string
{
    case InvoiceCreated = 'invoice.created';
    case InvoiceVoided = 'invoice.voided';
    case StockLow = 'stock.low';
    case GoodsReceived = 'grn.processed';
    case CreditNoteApproved = 'credit_note.approved';
}
