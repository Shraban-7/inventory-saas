<?php

namespace App\Domain\Exceptions;

use DomainException;

class ReportGenerationFailedException extends DomainException
{
    public function __construct()
    {
        parent::__construct('The report could not be generated.');
    }
}
