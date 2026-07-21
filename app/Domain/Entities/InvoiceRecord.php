<?php

namespace App\Domain\Entities;

final readonly class InvoiceRecord
{
    public function __construct(
        public int $branchId,
        public int $customerId,
        public string $number,
        public string $invoiceDate,
        public ?string $dueDate,
        public string $totalAmount,
        public string $taxAmount,
        public string $balanceDue,
        public ?string $notes,
    ) {}
}
