<?php

use App\Domain\Entities\Quantity;
use App\Domain\Entities\StockBalance;
use App\Domain\Entities\StockMovementType;
use App\Domain\Exceptions\InsufficientStockException;
use App\Domain\Repositories\StockRepository;
use App\Domain\Services\StockMovementService;

class FakeStockRepository implements StockRepository
{
    /** @var array<int, StockBalance> */
    public array $balances;

    /** @var list<int> */
    public array $lockedIds = [];

    /** @param array<int, StockBalance> $balances */
    public function __construct(array $balances)
    {
        $this->balances = $balances;
    }

    public function lockLevels(int $variantId, array $branchIds): array
    {
        $selected = array_intersect_key($this->balances, array_flip($branchIds));
        uasort($selected, fn (StockBalance $a, StockBalance $b): int => $a->id <=> $b->id);
        $this->lockedIds = array_values(array_map(fn (StockBalance $balance): int => $balance->id, $selected));

        return $selected;
    }

    public function saveBalance(StockBalance $balance): void
    {
        $this->balances[$balance->branchId] = $balance;
    }

    public function appendMovement(int $variantId, int $branchId, Quantity $delta, ?string $unitCost, StockMovementType $type, ?string $sourceType, ?int $sourceId): void {}
}

it('rejects deductions greater than on hand', function () {
    $repository = new FakeStockRepository([
        10 => new StockBalance(5, 1, 10, Quantity::from('2.0000')),
    ]);

    expect(fn () => (new StockMovementService($repository))->deduct(
        1, 10, '3.0000', null, StockMovementType::SalesDeduction, null, null,
    ))->toThrow(InsufficientStockException::class);
});

it('locks transfer stock levels in ascending row id order', function () {
    $repository = new FakeStockRepository([
        20 => new StockBalance(9, 1, 20, Quantity::from('0')),
        10 => new StockBalance(3, 1, 10, Quantity::from('5')),
    ]);

    (new StockMovementService($repository))->transfer(1, 10, 20, '1.0000', 7);

    expect($repository->lockedIds)->toBe([3, 9]);
});
