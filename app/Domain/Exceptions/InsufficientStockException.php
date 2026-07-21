<?php

namespace App\Domain\Exceptions;

use DomainException;

class InsufficientStockException extends DomainException
{
    public function __construct()
    {
        parent::__construct('The requested quantity exceeds available stock.');
    }
}
