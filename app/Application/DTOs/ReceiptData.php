<?php

namespace App\Application\DTOs;

use App\Domain\Entities\PaymentMethod;
use DateTimeImmutable;

final readonly class ReceiptData
{
    public function __construct(
        public int $invoiceId,
        public string $amount,
        public PaymentMethod $paymentMethod,
        public DateTimeImmutable $paymentDate,
        public ?string $reference,
    ) {}
}
