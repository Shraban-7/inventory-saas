<?php

namespace App\Domain\Services;

use App\Domain\Repositories\ManualJournalSequenceRepository;

final readonly class ManualJournalNumberService
{
    public function __construct(private ManualJournalSequenceRepository $sequences) {}

    public function next(int $year): string
    {
        $sequence = $this->sequences->next($year);

        return sprintf('JRN-MAN-%04d-%05d', $year, $sequence);
    }
}
