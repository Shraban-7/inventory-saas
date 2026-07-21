<?php

namespace App\Application\DTOs;

final readonly class StockTransferData
{
    /** @param list<array{variant_id: int, quantity: string}> $items */
    public function __construct(
        public int $fromBranchId,
        public int $toBranchId,
        public array $items,
        public string $idempotencyKey,
        public string $payloadHash,
        public ?int $userId,
    ) {}
}
