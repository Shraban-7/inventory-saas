<?php

namespace App\Application\DTOs;

use DateTimeImmutable;

final readonly class PurchaseOrderData
{
    /** @param list<PurchaseOrderItemData> $items */
    public function __construct(
        public int $branchId,
        public int $supplierId,
        public DateTimeImmutable $orderDate,
        public ?DateTimeImmutable $expectedDate,
        public ?string $notes,
        public array $items,
    ) {}
}
