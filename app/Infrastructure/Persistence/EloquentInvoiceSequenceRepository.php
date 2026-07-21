<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Repositories\InvoiceSequenceRepository;
use App\Infrastructure\Models\InvoiceSequence;
use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class EloquentInvoiceSequenceRepository implements InvoiceSequenceRepository
{
    public function next(int $year): int
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            return $this->nextWithNamedLock($connection, $year);
        }

        return $this->withinTransaction($connection, fn (): int => $this->incrementAtomically($year));
    }

    private function nextWithNamedLock(ConnectionInterface $connection, int $year): int
    {
        $lockName = 'invoice-seq:'.mb_substr(hash('sha256', current_tenant_id().':'.$year), 0, 48);
        $result = $connection->selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$lockName]);

        if ((int) ($result->acquired ?? 0) !== 1) {
            throw new RuntimeException('Could not acquire the invoice sequence lock.');
        }

        try {
            return $this->withinTransaction($connection, fn (): int => $this->incrementLocked($year));
        } finally {
            $released = $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);

            if ((int) ($released->released ?? 0) !== 1) {
                throw new RuntimeException('Could not release the invoice sequence lock.');
            }
        }
    }

    private function incrementLocked(int $year): int
    {
        InvoiceSequence::query()->firstOrCreate(
            ['tenant_id' => current_tenant_id(), 'year' => $year],
            ['sequence' => 0],
        );

        $sequence = InvoiceSequence::query()
            ->where('year', $year)
            ->lockForUpdate()
            ->firstOrFail();

        $sequence->sequence++;
        $sequence->save();

        return $sequence->sequence;
    }

    private function incrementAtomically(int $year): int
    {
        InvoiceSequence::query()->firstOrCreate(
            ['tenant_id' => current_tenant_id(), 'year' => $year],
            ['sequence' => 0],
        );

        InvoiceSequence::query()
            ->where('year', $year)
            ->increment('sequence');

        return (int) InvoiceSequence::query()
            ->where('year', $year)
            ->valueOrFail('sequence');
    }

    /**
     * @param  Closure(): int  $operation
     */
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
