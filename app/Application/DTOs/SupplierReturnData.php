<?php

namespace App\Application\DTOs;

final readonly class SupplierReturnData
{
    /** @param list<SupplierReturnItemData> $items */
    public function __construct(
        public int $branchId,
        public int $supplierId,
        public ?int $billId,
        public string $reason,
        public string $idempotencyKey,
        public string $payloadHash,
        public array $items,
    ) {}
}
