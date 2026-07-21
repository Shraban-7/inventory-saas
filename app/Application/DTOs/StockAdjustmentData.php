<?php

namespace App\Application\DTOs;

use App\Domain\Entities\StockMovementType;

final readonly class StockAdjustmentData
{
    public function __construct(
        public int $variantId,
        public int $branchId,
        public string $quantityDelta,
        public string $reason,
        public StockMovementType $type,
        public string $idempotencyKey,
        public string $payloadHash,
        public ?int $userId,
    ) {}
}
