<?php

namespace App\Domain\Events;

final readonly class CreditNoteApproved
{
    public function __construct(public int $creditNoteId) {}
}
