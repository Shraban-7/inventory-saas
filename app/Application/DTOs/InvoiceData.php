<?php

namespace App\Application\DTOs;

use DateTimeImmutable;

final readonly class InvoiceData
{
    /**
     * @param  list<InvoiceItemData>  $items
     */
    public function __construct(
        public int $branchId,
        public int $customerId,
        public DateTimeImmutable $invoiceDate,
        public ?DateTimeImmutable $dueDate,
        public ?string $notes,
        public array $items,
    ) {}
}
