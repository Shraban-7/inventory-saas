<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\CreditNoteItemRecord;
use App\Domain\Entities\CreditNoteRecord;
use App\Domain\Entities\InvoiceItemRecord;
use App\Domain\Entities\InvoiceRecord;

interface SalesRepository
{
    /**
     * @param  list<InvoiceItemRecord>  $items
     */
    public function createInvoice(InvoiceRecord $invoice, array $items): int;

    /**
     * @param  list<CreditNoteItemRecord>  $items
     */
    public function createCreditNoteDraft(CreditNoteRecord $creditNote, array $items): int;
}
