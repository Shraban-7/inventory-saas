<?php

namespace App\Domain\Events;

final readonly class InvoiceVoided
{
    public function __construct(public int $invoiceId) {}
}
