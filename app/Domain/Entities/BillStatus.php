<?php

namespace App\Domain\Entities;

enum BillStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Paid = 'paid';
    case PartiallyPaid = 'partially_paid';
    case Cancelled = 'cancelled';
}
