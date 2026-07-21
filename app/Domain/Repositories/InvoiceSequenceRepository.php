<?php

namespace App\Domain\Repositories;

interface InvoiceSequenceRepository
{
    public function next(int $year): int;
}
