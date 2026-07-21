<?php

namespace App\Domain\Entities;

enum StockMovementType: string
{
    case PurchaseReceipt = 'PURCHASE_RECEIPT';
    case SalesDeduction = 'SALES_DEDUCTION';
    case SalesReturn = 'SALES_RETURN';
    case PurchaseReturn = 'PURCHASE_RETURN';
    case StockAdjustmentIn = 'STOCK_ADJUSTMENT_IN';
    case StockAdjustmentOut = 'STOCK_ADJUSTMENT_OUT';
    case TransferOut = 'TRANSFER_OUT';
    case TransferIn = 'TRANSFER_IN';
    case OpeningBalance = 'OPENING_BALANCE';
}
