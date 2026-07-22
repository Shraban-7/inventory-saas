<?php

namespace App\Domain\Entities;

final readonly class VariantReorderProfile
{
    public function __construct(
        public int $variantId,
        public int $tenantId,
        public int $reorderPoint,
    ) {}
}
