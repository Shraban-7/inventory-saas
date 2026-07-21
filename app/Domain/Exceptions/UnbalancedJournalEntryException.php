<?php

namespace App\Domain\Exceptions;

use DomainException;

final class UnbalancedJournalEntryException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Journal entry debits and credits must be equal.');
    }
}
