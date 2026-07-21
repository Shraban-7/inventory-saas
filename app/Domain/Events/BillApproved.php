<?php

namespace App\Domain\Events;

final readonly class BillApproved
{
    public function __construct(public int $billId) {}
}
