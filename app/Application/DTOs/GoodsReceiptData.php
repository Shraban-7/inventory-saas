<?php

namespace App\Application\DTOs;

use DateTimeImmutable;

final readonly class GoodsReceiptData
{
    /** @param list<GrnItemData> $items */
    public function __construct(
        public int $branchId,
        public int $supplierId,
        public ?int $purchaseOrderId,
        public DateTimeImmutable $receivedAt,
        public ?string $notes,
        public string $idempotencyKey,
        public string $payloadHash,
        public array $items,
    ) {}
}
