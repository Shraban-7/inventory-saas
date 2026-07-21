<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\PurchasingDocumentType;

interface PurchasingSequenceRepository
{
    public function next(PurchasingDocumentType $documentType, int $year): int;
}
