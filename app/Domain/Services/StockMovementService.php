<?php

namespace App\Domain\Services;

use App\Domain\Entities\Quantity;
use App\Domain\Entities\StockBalance;
use App\Domain\Entities\StockMovementType;
use App\Domain\Exceptions\InsufficientStockException;
use App\Domain\Repositories\StockRepository;
use InvalidArgumentException;

final readonly class StockMovementService
{
    public function __construct(private StockRepository $repository) {}

    public function deduct(
        int $variantId,
        int $branchId,
        string $qty,
        ?string $unitCost,
        StockMovementType $type,
        ?string $sourceType,
        ?int $sourceId,
    ): void {
        $quantity = $this->positiveQuantity($qty);
        $balance = $this->repository->lockLevels($variantId, [$branchId])[$branchId];
        $this->deductLocked($balance, $quantity, $unitCost, $type, $sourceType, $sourceId);
    }

    public function add(
        int $variantId,
        int $branchId,
        string $qty,
        ?string $unitCost,
        StockMovementType $type,
        ?string $sourceType,
        ?int $sourceId,
    ): void {
        $quantity = $this->positiveQuantity($qty);
        $balance = $this->repository->lockLevels($variantId, [$branchId])[$branchId];
        $balance->quantity = $balance->quantity->add($quantity);
        $this->repository->saveBalance($balance);
        $this->repository->appendMovement($variantId, $branchId, $quantity, $unitCost, $type, $sourceType, $sourceId);
    }

    public function transfer(int $variantId, int $fromBranchId, int $toBranchId, string $qty, ?int $sourceId = null): void
    {
        if ($fromBranchId === $toBranchId) {
            throw new InvalidArgumentException('Transfer branches must be different.');
        }

        $quantity = $this->positiveQuantity($qty);
        $balances = $this->repository->lockLevels($variantId, [$fromBranchId, $toBranchId]);
        $this->deductLocked($balances[$fromBranchId], $quantity, null, StockMovementType::TransferOut, 'stock_transfer', $sourceId);
        $destination = $balances[$toBranchId];
        $destination->quantity = $destination->quantity->add($quantity);
        $this->repository->saveBalance($destination);
        $this->repository->appendMovement($variantId, $toBranchId, $quantity, null, StockMovementType::TransferIn, 'stock_transfer', $sourceId);
    }

    private function deductLocked(StockBalance $balance, Quantity $quantity, ?string $unitCost, StockMovementType $type, ?string $sourceType, ?int $sourceId): void
    {
        $remaining = $balance->quantity->subtract($quantity);

        if ($remaining->isNegative()) {
            throw new InsufficientStockException;
        }

        $balance->quantity = $remaining;
        $this->repository->saveBalance($balance);
        $this->repository->appendMovement($balance->variantId, $balance->branchId, Quantity::from('-'.$quantity->toDecimal()), $unitCost, $type, $sourceType, $sourceId);
    }

    private function positiveQuantity(string $quantity): Quantity
    {
        $value = Quantity::from($quantity);

        if (! $value->isPositive()) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }

        return $value;
    }
}
