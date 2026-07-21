<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\LotBalance;
use App\Domain\Entities\StockLevelKey;
use DateTimeImmutable;

interface InventoryLotRepository
{
    /**
     * Locks lot groups in canonical variant/branch order.
     *
     * @param  list<StockLevelKey>  $keys
     * @return array<string, list<LotBalance>>
     */
    public function lockLots(array $keys): array;

    /** @param list<StockLevelKey> $keys
     * @return array<int, string>
     */
    public function costingMethods(array $keys): array;

    public function save(LotBalance $lot): void;

    public function create(
        int $variantId,
        int $branchId,
        string $quantity,
        string $unitCost,
        DateTimeImmutable $receivedAt,
    ): void;
}
