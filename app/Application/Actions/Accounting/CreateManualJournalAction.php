<?php

namespace App\Application\Actions\Accounting;

use App\Application\DTOs\JournalEntryData;
use App\Application\DTOs\ManualJournalData;
use App\Domain\Services\ManualJournalNumberService;
use App\Infrastructure\Models\AuditLog;
use App\Infrastructure\Models\JournalEntry;
use Illuminate\Support\Facades\DB;

final readonly class CreateManualJournalAction
{
    public function __construct(
        private ManualJournalNumberService $numbers,
        private CreateJournalEntryAction $journals,
    ) {}

    public function handle(ManualJournalData $data, int $actingUserId): JournalEntry
    {
        return DB::transaction(function () use ($data, $actingUserId): JournalEntry {
            $number = $this->numbers->next((int) $data->postedAt->format('Y'));
            $entry = $this->journals->handle(new JournalEntryData(
                $data->branchId,
                $number,
                null,
                null,
                $data->postedAt,
                $data->description,
                $data->lines,
            ));

            AuditLog::query()->create([
                'user_id' => $actingUserId,
                'action' => 'MANUAL_JOURNAL_CREATED',
                'entity_type' => 'journal_entry',
                'entity_id' => $entry->getKey(),
                'old_values' => null,
                'new_values' => [
                    'journal_entry_number' => $entry->journal_entry_number,
                    'branch_id' => $entry->branch_id,
                    'posted_at' => $data->postedAt->format('Y-m-d'),
                    'line_count' => count($data->lines),
                ],
            ]);

            return $entry->load('lines');
        });
    }
}
