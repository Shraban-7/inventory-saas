<?php

namespace App\Domain\Services;

use App\Domain\Entities\LotAllocation;
use App\Domain\Entities\LotBalance;
use App\Domain\Entities\Quantity;
use App\Domain\Entities\StockDeductionResult;
use App\Domain\Exceptions\InsufficientStockException;
use InvalidArgumentException;

final class FifoCostCalculator
{
    /** @param list<LotBalance> $lots */
    public function oldestFirst(array $lots, string $quantity): StockDeductionResult
    {
        return $this->allocate($lots, $quantity, false);
    }

    /** @param list<LotBalance> $lots */
    public function newestFirst(array $lots, string $quantity): StockDeductionResult
    {
        return $this->allocate($lots, $quantity, true);
    }

    /** @param list<LotBalance> $lots */
    private function allocate(array $lots, string $quantity, bool $newestFirst): StockDeductionResult
    {
        $required = Quantity::from($quantity);

        if (! $required->isPositive()) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }

        usort($lots, static function (LotBalance $left, LotBalance $right) use ($newestFirst): int {
            $comparison = [$left->receivedAt->format('U.u'), $left->id]
                <=> [$right->receivedAt->format('U.u'), $right->id];

            return $newestFirst ? -$comparison : $comparison;
        });

        $available = Quantity::from(0);

        foreach ($lots as $lot) {
            if ($lot->quantity->isPositive()) {
                $available = $available->add($lot->quantity);
            }
        }

        if ($available->compare($required) < 0) {
            throw new InsufficientStockException;
        }

        $remaining = $required;
        $allocations = [];

        foreach ($lots as $lot) {
            if ($remaining->isZero()) {
                break;
            }

            if (! $lot->quantity->isPositive()) {
                continue;
            }

            $allocated = $lot->quantity->compare($remaining) <= 0 ? $lot->quantity : $remaining;
            $allocations[] = new LotAllocation($lot->id, $allocated, $lot->unitCost, $lot->receivedAt);
            $remaining = $remaining->subtract($allocated);
        }

        return StockDeductionResult::fromAllocations($required, $allocations);
    }
}
