<?php

namespace App\Domain\Entities;

enum BulkImportType: string
{
    case Products = 'products';
    case StockAdjustments = 'stock_adjustments';
}
