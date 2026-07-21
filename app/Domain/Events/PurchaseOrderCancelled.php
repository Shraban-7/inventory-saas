<?php

namespace App\Domain\Events;

final readonly class PurchaseOrderCancelled
{
    public function __construct(public int $purchaseOrderId) {}
}
