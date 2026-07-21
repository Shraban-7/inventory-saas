<?php

namespace App\Application\Actions\Accounting;

use App\Application\DTOs\JournalEntryData;
use App\Application\DTOs\JournalEntryLineData;
use App\Domain\Entities\Money;
use App\Domain\Exceptions\InvalidJournalEntryException;
use App\Domain\Exceptions\TransactionRequiredException;
use App\Domain\Exceptions\UnbalancedJournalEntryException;
use App\Infrastructure\Models\Branch;
use App\Infrastructure\Models\ChartOfAccount;
use App\Infrastructure\Models\JournalEntry;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

class CreateJournalEntryAction
{
    public function handle(JournalEntryData $data): JournalEntry
    {
        if (DB::transactionLevel() < 1) {
            throw new TransactionRequiredException('Journal entries must be created inside a database transaction.');
        }

        if (count($data->lines) < 2) {
            throw new InvalidJournalEntryException('A journal entry must contain at least two lines.');
        }

        if ($data->number === '' || $data->description === '') {
            throw new InvalidJournalEntryException('Journal number and description are required.');
        }

        if ($data->referenceId < 1 || Relation::getMorphedModel($data->referenceType) === null) {
            throw new InvalidJournalEntryException('Journal reference must use a canonical morph alias and valid ID.');
        }

        Branch::query()->findOrFail($data->branchId);

        $debitTotal = Money::zero();
        $creditTotal = Money::zero();
        $normalizedLines = [];
        $accountIds = [];

        foreach ($data->lines as $line) {
            [$debit, $credit] = $this->validateLine($line);
            $debitTotal = $debitTotal->add($debit);
            $creditTotal = $creditTotal->add($credit);
            $normalizedLines[] = [
                'coa_id' => $line->coaId,
                'debit' => $debit->toDecimal(),
                'credit' => $credit->toDecimal(),
                'description' => $line->description,
            ];
            $accountIds[$line->coaId] = $line->coaId;
        }

        if ($debitTotal->compare($creditTotal) !== 0) {
            throw new UnbalancedJournalEntryException;
        }

        sort($accountIds);
        $accounts = ChartOfAccount::query()
            ->whereKey($accountIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);

        if ($accounts->count() !== count($accountIds)) {
            throw new InvalidJournalEntryException('Every journal account must belong to the current tenant.');
        }

        $entry = JournalEntry::query()->create([
            'branch_id' => $data->branchId,
            'journal_entry_number' => $data->number,
            'description' => $data->description,
            'reference_type' => $data->referenceType,
            'reference_id' => $data->referenceId,
            'posted_at' => $data->postedAt->format('Y-m-d'),
        ]);

        $entry->lines()->createMany($normalizedLines);

        return $entry;
    }

    /**
     * @return array{Money, Money}
     */
    private function validateLine(JournalEntryLineData $line): array
    {
        if ($line->coaId < 1) {
            throw new InvalidJournalEntryException('Journal account IDs must be positive.');
        }

        $debit = Money::fromDecimal($line->debit);
        $credit = Money::fromDecimal($line->credit);
        $debitOnly = $debit->isPositive() && $credit->isZero();
        $creditOnly = $credit->isPositive() && $debit->isZero();

        if (! $debitOnly && ! $creditOnly) {
            throw new InvalidJournalEntryException('Each journal line must contain exactly one positive debit or credit.');
        }

        return [$debit, $credit];
    }
}
