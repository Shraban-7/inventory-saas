<?php

namespace App\Application\DTOs;

use App\Domain\Entities\PurchasePaymentMethod;
use DateTimeImmutable;

final readonly class BillPaymentData
{
    public function __construct(
        public string $amount,
        public PurchasePaymentMethod $paymentMethod,
        public DateTimeImmutable $paymentDate,
        public ?string $reference = null,
    ) {}
}
