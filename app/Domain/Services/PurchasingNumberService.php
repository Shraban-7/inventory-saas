<?php

namespace App\Domain\Services;

use App\Domain\Entities\PurchasingDocumentType;
use App\Domain\Repositories\PurchasingSequenceRepository;
use InvalidArgumentException;

final readonly class PurchasingNumberService
{
    public function __construct(private PurchasingSequenceRepository $sequences) {}

    public function next(PurchasingDocumentType $documentType, int $year): string
    {
        return $this->format($documentType, $year, $this->sequences->next($documentType, $year));
    }

    public function format(PurchasingDocumentType $documentType, int $year, int $sequence): string
    {
        if ($year < 1 || $year > 9999) {
            throw new InvalidArgumentException('Purchasing document year must be between 1 and 9999.');
        }

        if ($sequence < 1) {
            throw new InvalidArgumentException('Purchasing document sequence must be positive.');
        }

        return sprintf('%s-%04d-%05d', strtoupper($documentType->value), $year, $sequence);
    }
}
