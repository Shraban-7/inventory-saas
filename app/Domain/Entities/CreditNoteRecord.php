<?php

namespace App\Domain\Entities;

final readonly class CreditNoteRecord
{
    public function __construct(
        public int $branchId,
        public int $customerId,
        public ?int $invoiceId,
        public string $reason,
        public string $totalAmount,
    ) {}
}
