<?php

namespace App\Domain\Entities;

enum CostingMethod: string
{
    case Fifo = 'fifo';
    case Avco = 'avco';
}
