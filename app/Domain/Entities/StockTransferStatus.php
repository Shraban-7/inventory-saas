<?php

namespace App\Domain\Entities;

enum StockTransferStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
