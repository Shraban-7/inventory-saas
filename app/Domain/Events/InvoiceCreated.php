<?php

namespace App\Domain\Events;

final readonly class InvoiceCreated
{
    public function __construct(public int $invoiceId) {}
}
