<?php

namespace App\Domain\Entities;

enum ChartOfAccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Revenue = 'revenue';
    case Expense = 'expense';
    case CostOfGoodsSold = 'cogs';
}
