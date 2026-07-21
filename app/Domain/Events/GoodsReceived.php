<?php

namespace App\Domain\Events;

final readonly class GoodsReceived
{
    public function __construct(public int $goodsReceiptId) {}
}
