<?php

namespace App\Domain\Events;

final readonly class ReceiptRecorded
{
    public function __construct(public int $receiptId) {}
}
