<?php

namespace App\Application\DTOs;

use DateTimeImmutable;

final readonly class ManualJournalData
{
    /**
     * @param  list<JournalEntryLineData>  $lines
     */
    public function __construct(
        public int $branchId,
        public DateTimeImmutable $postedAt,
        public string $description,
        public array $lines,
    ) {}
}
