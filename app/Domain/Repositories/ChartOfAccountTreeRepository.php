<?php

namespace App\Domain\Repositories;

interface ChartOfAccountTreeRepository
{
    /** @return list<mixed> */
    public function tree(int $tenantId): array;

    public function invalidate(int $tenantId): void;
}
