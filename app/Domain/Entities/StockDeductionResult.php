<?php

namespace App\Domain\Entities;

final readonly class StockDeductionResult
{
    /**
     * @param  list<LotAllocation>  $allocations
     */
    public function __construct(
        public Money $totalCost,
        public string $weightedUnitCost,
        public array $allocations = [],
    ) {}

    /** @param list<LotAllocation> $allocations */
    public static function fromAllocations(Quantity $quantity, array $allocations): self
    {
        $total = Money::zero();
        $unitCosts = [];

        foreach ($allocations as $allocation) {
            $total = $total->add($allocation->totalCost());
            $unitCosts[$allocation->unitCost] = true;
        }

        $weightedCost = count($unitCosts) === 1
            ? $allocations[0]->unitCost
            : $total->unitPriceForQuantity($quantity);

        return new self($total, $weightedCost, $allocations);
    }

    public static function fromUnitCost(Quantity $quantity, ?string $unitCost): self
    {
        $cost = $unitCost ?? '0.0000';
        $total = Money::quantityTimesPrice($quantity->toDecimal(), $cost);

        return new self($total, $cost);
    }
}
