<?php

namespace App\Domain\Exceptions;

use DomainException;

class ImmutableRecordException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Immutable ledger records cannot be changed or deleted.');
    }
}
