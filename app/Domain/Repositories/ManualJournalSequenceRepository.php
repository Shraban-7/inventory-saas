<?php

namespace App\Domain\Repositories;

interface ManualJournalSequenceRepository
{
    public function next(int $year): int;
}
