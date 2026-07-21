<?php

namespace App\Domain\Services;

use App\Domain\Repositories\InvoiceSequenceRepository;
use InvalidArgumentException;

final readonly class InvoiceNumberService
{
    public function __construct(private InvoiceSequenceRepository $sequences) {}

    public function next(int $year): string
    {
        if ($year < 1 || $year > 9999) {
            throw new InvalidArgumentException('Invoice year must be between 1 and 9999.');
        }

        $sequence = $this->sequences->next($year);

        if ($sequence < 1) {
            throw new InvalidArgumentException('Invoice sequence must be positive.');
        }

        return sprintf('INV-%04d-%05d', $year, $sequence);
    }
}
