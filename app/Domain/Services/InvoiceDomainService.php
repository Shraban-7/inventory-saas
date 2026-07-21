<?php

namespace App\Domain\Services;

use App\Domain\Entities\InvoiceLineTotal;
use App\Domain\Entities\Money;
use App\Domain\Entities\PricedInvoiceItem;
use App\Domain\Entities\Quantity;
use App\Domain\Entities\Totals;
use InvalidArgumentException;

final class InvoiceDomainService
{
    /**
     * @param  list<PricedInvoiceItem>  $items
     */
    public function calculate(array $items): Totals
    {
        if ($items === []) {
            throw new InvalidArgumentException('An invoice must contain at least one item.');
        }

        $subtotal = Money::zero();
        $taxTotal = Money::zero();
        $costTotal = Money::zero();
        $lines = [];

        foreach ($items as $item) {
            $quantity = Quantity::from($item->quantity);

            if (! $quantity->isPositive()) {
                throw new InvalidArgumentException('Invoice item quantity must be greater than zero.');
            }

            $net = Money::quantityTimesPrice($item->quantity, $item->unitPrice);
            $cost = Money::quantityTimesPrice($item->quantity, $item->currentCost);
            $tax = $item->taxRate === null ? Money::zero() : $net->percentage($item->taxRate);
            $gross = $net->add($tax);

            if (($item->taxId === null) !== ($item->taxRate === null)
                || ($item->taxId === null) !== ($item->taxAccountId === null)) {
                throw new InvalidArgumentException('Tax snapshots must include an ID, rate, and account together.');
            }

            $lines[] = new InvoiceLineTotal(
                $item->variantId,
                $quantity->toDecimal(),
                $item->unitPrice,
                $item->currentCost,
                $item->taxId,
                $item->taxRate,
                $item->taxAccountId,
                $net,
                $tax,
                $gross,
                $cost,
            );

            $subtotal = $subtotal->add($net);
            $taxTotal = $taxTotal->add($tax);
            $costTotal = $costTotal->add($cost);
        }

        return new Totals(
            $subtotal,
            $taxTotal,
            $subtotal->add($taxTotal),
            $costTotal,
            $lines,
        );
    }
}
