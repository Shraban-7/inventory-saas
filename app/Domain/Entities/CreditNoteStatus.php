<?php

namespace App\Domain\Entities;

enum CreditNoteStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Cancelled = 'cancelled';
}
