<?php

namespace App\Domain\Services;

use App\Domain\Entities\BillLineTotal;
use App\Domain\Entities\BillTotals;
use App\Domain\Entities\Money;
use App\Domain\Entities\PricedBillItem;
use App\Domain\Entities\Quantity;
use InvalidArgumentException;

final class BillDomainService
{
    /** @param list<PricedBillItem> $items */
    public function calculate(array $items): BillTotals
    {
        if ($items === []) {
            throw new InvalidArgumentException('A bill must contain at least one item.');
        }

        $variantIds = [];
        $gross = Money::zero();
        $taxTotal = Money::zero();
        $lines = [];

        foreach ($items as $item) {
            if (isset($variantIds[$item->variantId])) {
                throw new InvalidArgumentException('A bill cannot contain duplicate product variants.');
            }

            $variantIds[$item->variantId] = true;
            $quantity = Quantity::from($item->quantity);

            if (! $quantity->isPositive()) {
                throw new InvalidArgumentException('Bill item quantity must be greater than zero.');
            }

            $lineGross = Money::quantityTimesPrice($quantity->toDecimal(), $item->unitCost);

            if (! $lineGross->isPositive()) {
                throw new InvalidArgumentException('Bill item unit cost must be greater than zero.');
            }

            if (($item->taxId === null) !== ($item->taxRate === null)
                || ($item->taxId === null) !== ($item->taxAccountId === null)) {
                throw new InvalidArgumentException('Tax snapshots must include an ID, rate, and account together.');
            }

            $lineTax = $item->taxRate === null ? Money::zero() : $lineGross->percentage($item->taxRate);
            $lineTotal = $lineGross->add($lineTax);
            $lines[] = new BillLineTotal(
                $item->variantId,
                $quantity->toDecimal(),
                $item->unitCost,
                $item->taxId,
                $item->taxRate,
                $item->taxAccountId,
                $lineGross,
                $lineTax,
                $lineTotal,
            );
            $gross = $gross->add($lineGross);
            $taxTotal = $taxTotal->add($lineTax);
        }

        return new BillTotals($gross, $taxTotal, $gross->add($taxTotal), $lines);
    }
}
