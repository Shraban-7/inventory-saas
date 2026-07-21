<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\StockBalance;
use App\Domain\Entities\StockLevelKey;

interface BulkStockRepository extends StockRepository
{
    /**
     * @param  list<StockLevelKey>  $keys
     * @return array<string, StockBalance>
     */
    public function lockLevelPairs(array $keys): array;
}
