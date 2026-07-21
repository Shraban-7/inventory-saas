<?php

namespace App\Application\DTOs;

use DateTimeImmutable;

final readonly class JournalEntryData
{
    /**
     * @param  list<JournalEntryLineData>  $lines
     */
    public function __construct(
        public int $branchId,
        public string $number,
        public string $referenceType,
        public int $referenceId,
        public DateTimeImmutable $postedAt,
        public string $description,
        public array $lines,
    ) {}
}
