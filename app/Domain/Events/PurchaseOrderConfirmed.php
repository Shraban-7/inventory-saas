<?php

namespace App\Domain\Events;

final readonly class PurchaseOrderConfirmed
{
    public function __construct(public int $purchaseOrderId) {}
}
