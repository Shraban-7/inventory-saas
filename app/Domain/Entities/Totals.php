<?php

namespace App\Domain\Entities;

final readonly class Totals
{
    /**
     * @param  list<InvoiceLineTotal>  $lines
     */
    public function __construct(
        public Money $subtotal,
        public Money $tax,
        public Money $total,
        public Money $cost,
        public array $lines,
    ) {}
}
