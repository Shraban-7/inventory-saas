<?php

namespace App\Domain\Entities;

enum SupplierReturnStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
}
