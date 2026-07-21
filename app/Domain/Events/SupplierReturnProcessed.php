<?php

namespace App\Domain\Events;

final readonly class SupplierReturnProcessed
{
    public function __construct(public int $supplierReturnId) {}
}
