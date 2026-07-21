<?php

namespace App\Domain\Entities;

final readonly class BillTotals
{
    /** @param list<BillLineTotal> $lines */
    public function __construct(
        public Money $gross,
        public Money $tax,
        public Money $total,
        public array $lines,
    ) {}
}
