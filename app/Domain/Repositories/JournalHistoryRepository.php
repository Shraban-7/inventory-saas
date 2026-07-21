<?php

namespace App\Domain\Repositories;

use App\Infrastructure\Models\JournalEntry;
use Illuminate\Pagination\CursorPaginator;

interface JournalHistoryRepository
{
    /**
     * @param  list<int>|null  $branchIds
     * @return CursorPaginator<int, JournalEntry>
     */
    public function paginate(
        ?array $branchIds,
        ?string $dateFrom,
        ?string $dateTo,
        ?string $referenceType,
        int $perPage,
    ): CursorPaginator;

    public function find(int $journalEntryId): ?JournalEntry;
}
