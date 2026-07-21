<?php

namespace App\Domain\Exceptions;

use DomainException;

class ReportNotReadyException extends DomainException
{
    public function __construct()
    {
        parent::__construct('The report is still being generated.');
    }
}
