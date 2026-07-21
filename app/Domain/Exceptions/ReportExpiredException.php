<?php

namespace App\Domain\Exceptions;

use DomainException;

class ReportExpiredException extends DomainException
{
    public function __construct()
    {
        parent::__construct('The report result has expired.');
    }
}
