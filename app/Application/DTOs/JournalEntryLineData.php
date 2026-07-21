<?php

namespace App\Application\DTOs;

final readonly class JournalEntryLineData
{
    public function __construct(
        public int $coaId,
        public string $debit,
        public string $credit,
        public ?string $description,
    ) {}
}
