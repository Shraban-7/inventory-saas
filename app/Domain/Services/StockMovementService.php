<?php

namespace App\Domain\Services;

use App\Domain\Contracts\DeferredDomainEventPublisher;
use App\Domain\Entities\LotBalance;
use App\Domain\Entities\Quantity;
use App\Domain\Entities\StockDeductionResult;
use App\Domain\Entities\StockLevelKey;
use App\Domain\Entities\StockMovementData;
use App\Domain\Entities\StockMovementType;
use App\Domain\Entities\VariantReorderProfile;
use App\Domain\Events\StockLow;
use App\Domain\Exceptions\InsufficientStockException;
use App\Domain\Repositories\BulkStockRepository;
use App\Domain\Repositories\InventoryLotRepository;
use App\Domain\Repositories\StockRepository;
use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;

final readonly class StockMovementService
{
    public function __construct(
        private StockRepository $repository,
        private ?InventoryLotRepository $lots = null,
        ?FifoCostCalculator $fifo = null,
        private ?DeferredDomainEventPublisher $events = null,
    ) {
        $this->fifo = $fifo ?? new FifoCostCalculator;
    }

    private FifoCostCalculator $fifo;

    public function deduct(
        int $variantId,
        int $branchId,
        string $qty,
        ?string $unitCost,
        StockMovementType $type,
        ?string $sourceType,
        ?int $sourceId,
    ): StockDeductionResult {
        if ($this->lots === null) {
            $quantity = $this->positiveQuantity($qty);
            $balance = $this->repository->lockLevels($variantId, [$branchId])[$branchId];
            $previous = $balance->quantity;
            $remaining = $previous->subtract($quantity);

            if ($remaining->isNegative()) {
                throw new InsufficientStockException;
            }

            $balance->quantity = $remaining;
            $this->repository->saveBalance($balance);
            $movementId = $this->repository->appendMovement($variantId, $branchId, Quantity::from('-'.$quantity->toDecimal()), $unitCost, $type, $sourceType, $sourceId);
            $this->publishCrossingAlert($variantId, $branchId, $previous, $remaining, $movementId);

            return StockDeductionResult::fromUnitCost($quantity, $unitCost);
        }

        return $this->bulkDeduct([
            new StockMovementData($variantId, $branchId, $qty, $unitCost, $type, $sourceType, $sourceId),
        ])["{$variantId}:{$branchId}"];
    }

    public function add(
        int $variantId,
        int $branchId,
        string $qty,
        ?string $unitCost,
        StockMovementType $type,
        ?string $sourceType,
        ?int $sourceId,
        ?DateTimeImmutable $receivedAt = null,
    ): void {
        $this->bulkAdd([
            new StockMovementData($variantId, $branchId, $qty, $unitCost, $type, $sourceType, $sourceId, $receivedAt),
        ]);
    }

    public function transfer(int $variantId, int $fromBranchId, int $toBranchId, string $qty, ?int $sourceId = null): void
    {
        if ($fromBranchId === $toBranchId) {
            throw new InvalidArgumentException('Transfer branches must be different.');
        }

        $quantity = $this->positiveQuantity($qty);
        $balances = $this->repository->lockLevels($variantId, [$fromBranchId, $toBranchId]);

        if ($this->lots === null) {
            $previous = $balances[$fromBranchId]->quantity;
            $remaining = $previous->subtract($quantity);

            if ($remaining->isNegative()) {
                throw new InsufficientStockException;
            }

            $balances[$fromBranchId]->quantity = $remaining;
            $this->repository->saveBalance($balances[$fromBranchId]);
            $movementId = $this->repository->appendMovement($variantId, $fromBranchId, Quantity::from('-'.$quantity->toDecimal()), null, StockMovementType::TransferOut, 'stock_transfer', $sourceId);
            $this->publishCrossingAlert($variantId, $fromBranchId, $previous, $remaining, $movementId);
            $balances[$toBranchId]->quantity = $balances[$toBranchId]->quantity->add($quantity);
            $this->repository->saveBalance($balances[$toBranchId]);
            $this->repository->appendMovement($variantId, $toBranchId, $quantity, null, StockMovementType::TransferIn, 'stock_transfer', $sourceId);

            return;
        }

        $key = new StockLevelKey($variantId, $fromBranchId);
        $destinationKey = new StockLevelKey($variantId, $toBranchId);
        $lotRepository = $this->lotRepository();
        $method = $lotRepository->costingMethods([$key, $destinationKey])[$variantId] ?? 'avco';
        $lotGroups = $method === 'fifo' ? $lotRepository->lockLots([$key, $destinationKey]) : [];
        $sourceLots = $lotGroups[$key->value()] ?? [];
        $result = $method === 'fifo'
            ? $this->calculateAllocation($sourceLots, $quantity, StockMovementType::TransferOut)
            : StockDeductionResult::fromUnitCost($quantity, null);

        $previous = $balances[$fromBranchId]->quantity;
        $remaining = $previous->subtract($quantity);

        if ($remaining->isNegative()) {
            throw new InsufficientStockException;
        }

        $balances[$fromBranchId]->quantity = $remaining;
        $this->persistAllocations($sourceLots, $result);
        $this->repository->saveBalance($balances[$fromBranchId]);
        $movementId = $this->repository->appendMovement($variantId, $fromBranchId, Quantity::from('-'.$quantity->toDecimal()), $result->weightedUnitCost, StockMovementType::TransferOut, 'stock_transfer', $sourceId);
        $this->publishCrossingAlert($variantId, $fromBranchId, $previous, $remaining, $movementId);

        $destination = $balances[$toBranchId];
        $destination->quantity = $destination->quantity->add($quantity);
        $this->repository->saveBalance($destination);
        $this->repository->appendMovement($variantId, $toBranchId, $quantity, $result->weightedUnitCost, StockMovementType::TransferIn, 'stock_transfer', $sourceId);

        if ($method === 'fifo') {
            foreach ($result->allocations as $allocation) {
                $lotRepository->create($variantId, $toBranchId, $allocation->quantity->toDecimal(), $allocation->unitCost, $allocation->receivedAt);
            }
        }
    }

    /**
     * @param  list<StockMovementData>  $movements
     * @return array<string, StockDeductionResult>
     */
    public function bulkDeduct(array $movements): array
    {
        $quantities = $this->validateBulkMovements($movements);
        $keys = array_map(static fn (StockMovementData $movement) => $movement->key(), $movements);
        $balances = $this->bulkRepository()->lockLevelPairs(array_map(
            static fn (StockMovementData $movement) => $movement->key(),
            $movements,
        ));
        $lotRepository = $this->lotRepository();
        $methods = $lotRepository->costingMethods($keys);
        $lotGroups = $lotRepository->lockLots(array_values(array_filter(
            $keys,
            static fn ($key): bool => ($methods[$key->variantId] ?? 'avco') === 'fifo',
        )));
        $profiles = $this->events === null
            ? []
            : $this->repository->reorderProfiles(array_map(
                static fn (StockMovementData $movement): int => $movement->variantId,
                $movements,
            ));
        $results = [];
        $previousQuantities = [];

        foreach ($movements as $movement) {
            $key = $movement->key()->value();
            $previousQuantities[$key] = $balances[$key]->quantity;
            $remaining = $previousQuantities[$key]->subtract($quantities[$key]);

            if ($remaining->isNegative()) {
                throw new InsufficientStockException;
            }

            $balances[$key]->quantity = $remaining;
            $results[$key] = ($methods[$movement->variantId] ?? 'avco') === 'fifo'
                ? $this->calculateAllocation($lotGroups[$key] ?? [], $quantities[$key], $movement->type)
                : StockDeductionResult::fromUnitCost($quantities[$key], $movement->unitCost);
        }

        foreach ($movements as $movement) {
            $key = $movement->key()->value();
            $this->persistAllocations($lotGroups[$key] ?? [], $results[$key]);
            $this->repository->saveBalance($balances[$key]);
            $movementId = $this->repository->appendMovement(
                $movement->variantId,
                $movement->branchId,
                Quantity::from('-'.$quantities[$key]->toDecimal()),
                $results[$key]->weightedUnitCost,
                $movement->type,
                $movement->sourceType,
                $movement->sourceId,
            );
            $this->publishCrossingAlert(
                $movement->variantId,
                $movement->branchId,
                $previousQuantities[$key],
                $balances[$key]->quantity,
                $movementId,
                $profiles[$movement->variantId] ?? null,
            );
        }

        return $results;
    }

    /**
     * @param  list<StockMovementData>  $movements
     */
    public function bulkAdd(array $movements): void
    {
        $quantities = $this->validateBulkMovements($movements);
        $keys = array_map(
            static fn (StockMovementData $movement) => $movement->key(),
            $movements,
        );
        $balances = $this->bulkRepository()->lockLevelPairs($keys);
        $lotRepository = $this->lotRepository();
        $methods = $lotRepository->costingMethods($keys);

        foreach ($movements as $movement) {
            if (($methods[$movement->variantId] ?? 'avco') === 'fifo' && $movement->unitCost === null) {
                throw new InvalidArgumentException('FIFO inbound movements require a unit cost.');
            }
        }

        foreach ($movements as $movement) {
            $key = $movement->key()->value();
            $balances[$key]->quantity = $balances[$key]->quantity->add($quantities[$key]);
        }

        foreach ($movements as $movement) {
            $key = $movement->key()->value();
            $this->repository->saveBalance($balances[$key]);
            $this->repository->appendMovement(
                $movement->variantId,
                $movement->branchId,
                $quantities[$key],
                $movement->unitCost,
                $movement->type,
                $movement->sourceType,
                $movement->sourceId,
            );

            if (($methods[$movement->variantId] ?? 'avco') === 'fifo') {
                $lotRepository->create(
                    $movement->variantId,
                    $movement->branchId,
                    $quantities[$key]->toDecimal(),
                    $movement->unitCost ?? throw new LogicException('Validated FIFO unit cost is missing.'),
                    $movement->receivedAt ?? new DateTimeImmutable,
                );
            }
        }
    }

    private function positiveQuantity(string $quantity): Quantity
    {
        $value = Quantity::from($quantity);

        if (! $value->isPositive()) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }

        return $value;
    }

    /**
     * @param  list<StockMovementData>  $movements
     * @return array<string, Quantity>
     */
    private function validateBulkMovements(array $movements): array
    {
        $quantities = [];

        foreach ($movements as $movement) {
            $key = $movement->key()->value();

            if (isset($quantities[$key])) {
                throw new InvalidArgumentException("Duplicate stock movement pair {$key}.");
            }

            $quantities[$key] = $this->positiveQuantity($movement->quantity);
        }

        return $quantities;
    }

    private function bulkRepository(): BulkStockRepository
    {
        if (! $this->repository instanceof BulkStockRepository) {
            throw new LogicException('The configured stock repository does not support bulk locking.');
        }

        return $this->repository;
    }

    /** @param list<LotBalance> $lots */
    private function calculateAllocation(array $lots, Quantity $quantity, StockMovementType $type): StockDeductionResult
    {
        return $type === StockMovementType::PurchaseReturn
            ? $this->fifo->newestFirst($lots, $quantity->toDecimal())
            : $this->fifo->oldestFirst($lots, $quantity->toDecimal());
    }

    /** @param list<LotBalance> $lots */
    private function persistAllocations(array $lots, StockDeductionResult $result): void
    {
        $byId = [];

        foreach ($lots as $lot) {
            $byId[$lot->id] = $lot;
        }

        foreach ($result->allocations as $allocation) {
            $lot = $byId[$allocation->lotId];
            $lot->quantity = $lot->quantity->subtract($allocation->quantity);
            $this->lotRepository()->save($lot);
        }
    }

    private function lotRepository(): InventoryLotRepository
    {
        return $this->lots ?? throw new LogicException('The configured stock service does not support inventory lots.');
    }

    private function publishCrossingAlert(
        int $variantId,
        int $branchId,
        Quantity $previous,
        Quantity $resulting,
        int $stockMovementId,
        ?VariantReorderProfile $profile = null,
    ): void {
        if ($this->events === null) {
            return;
        }

        $profile ??= $this->repository->reorderProfiles([$variantId])[$variantId]
            ?? throw new LogicException("Reorder profile missing for variant {$variantId}.");
        $threshold = Quantity::from((string) $profile->reorderPoint);

        if ($previous->compare($threshold) <= 0 || $resulting->compare($threshold) > 0) {
            return;
        }

        $this->events->publishAfterCommit(new StockLow(
            $profile->tenantId,
            $variantId,
            $branchId,
            $resulting->toDecimal(),
            $profile->reorderPoint,
            $stockMovementId,
        ));
    }
}
