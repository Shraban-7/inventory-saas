<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Repositories\ManualJournalSequenceRepository;
use App\Infrastructure\Models\ManualJournalSequence;
use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class EloquentManualJournalSequenceRepository implements ManualJournalSequenceRepository
{
    public function next(int $year): int
    {
        $connection = DB::connection();

        if (in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            return $this->nextWithNamedLock($connection, $year);
        }

        return $this->withinTransaction($connection, fn (): int => $this->incrementAtomically($year));
    }

    private function nextWithNamedLock(ConnectionInterface $connection, int $year): int
    {
        $lockName = 'manual-journal-seq:'.mb_substr(hash('sha256', current_tenant_id().':'.$year), 0, 40);
        $result = $connection->selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$lockName]);

        if ((int) ($result->acquired ?? 0) !== 1) {
            throw new RuntimeException('Could not acquire the manual journal sequence lock.');
        }

        try {
            return $this->withinTransaction($connection, fn (): int => $this->incrementLocked($year));
        } finally {
            $released = $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);

            if ((int) ($released->released ?? 0) !== 1) {
                throw new RuntimeException('Could not release the manual journal sequence lock.');
            }
        }
    }

    private function incrementLocked(int $year): int
    {
        $timestamp = now();
        ManualJournalSequence::query()->insertOrIgnore([[
            'tenant_id' => current_tenant_id(),
            'year' => $year,
            'sequence' => 0,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]]);

        $sequence = ManualJournalSequence::query()
            ->where('year', $year)
            ->lockForUpdate()
            ->firstOrFail();

        $sequence->sequence++;
        $sequence->save();

        return $sequence->sequence;
    }

    private function incrementAtomically(int $year): int
    {
        ManualJournalSequence::query()->firstOrCreate(
            ['tenant_id' => current_tenant_id(), 'year' => $year],
            ['sequence' => 0],
        );
        ManualJournalSequence::query()->where('year', $year)->increment('sequence');

        return (int) ManualJournalSequence::query()
            ->where('year', $year)
            ->valueOrFail('sequence');
    }

    /** @param  Closure(): int  $operation */
    private function withinTransaction(ConnectionInterface $connection, Closure $operation): int
    {
        if ($connection->transactionLevel() > 0) {
            return $operation();
        }

        return $connection->transaction(
            static fn (ConnectionInterface $unused): int => $operation(),
        );
    }
}
