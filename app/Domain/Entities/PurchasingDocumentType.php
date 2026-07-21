<?php

namespace App\Domain\Entities;

enum PurchasingDocumentType: string
{
    case PurchaseOrder = 'po';
    case GoodsReceiptNote = 'grn';
}
