<?php

namespace App\Application\DTOs;

use DateTimeImmutable;

final readonly class BillData
{
    /** @param list<BillItemData> $items */
    public function __construct(
        public int $branchId,
        public int $supplierId,
        public ?int $grnId,
        public string $billNumber,
        public DateTimeImmutable $billDate,
        public ?DateTimeImmutable $dueDate,
        public array $items,
    ) {}
}
