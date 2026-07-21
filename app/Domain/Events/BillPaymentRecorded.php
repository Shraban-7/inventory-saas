<?php

namespace App\Domain\Events;

final readonly class BillPaymentRecorded
{
    public function __construct(
        public int $billId,
        public int $paymentId,
    ) {}
}
