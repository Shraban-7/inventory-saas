<?php

namespace App\Domain\Entities;

enum PurchasePaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Cheque = 'cheque';
    case Other = 'other';
}
