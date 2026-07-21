<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Entities\PurchasingDocumentType;
use App\Domain\Repositories\PurchasingSequenceRepository;
use App\Infrastructure\Models\PurchasingSequence;
use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class EloquentPurchasingSequenceRepository implements PurchasingSequenceRepository
{
    public function next(PurchasingDocumentType $documentType, int $year): int
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            return $this->nextWithNamedLock($connection, $documentType, $year);
        }

        return $this->withinTransaction(
            $connection,
            fn (): int => $this->incrementAtomically($documentType, $year),
        );
    }

    private function nextWithNamedLock(
        ConnectionInterface $connection,
        PurchasingDocumentType $documentType,
        int $year,
    ): int {
        $identity = current_tenant_id().':'.$documentType->value.':'.$year;
        $lockName = 'purchasing-seq:'.mb_substr(hash('sha256', $identity), 0, 48);
        $result = $connection->selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$lockName]);

        if ((int) ($result->acquired ?? 0) !== 1) {
            throw new RuntimeException('Could not acquire the purchasing sequence lock.');
        }

        try {
            return $this->withinTransaction(
                $connection,
                fn (): int => $this->incrementLocked($documentType, $year),
            );
        } finally {
            $released = $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);

            if ((int) ($released->released ?? 0) !== 1) {
                throw new RuntimeException('Could not release the purchasing sequence lock.');
            }
        }
    }

    private function incrementLocked(PurchasingDocumentType $documentType, int $year): int
    {
        PurchasingSequence::query()->firstOrCreate(
            [
                'tenant_id' => current_tenant_id(),
                'document_type' => $documentType,
                'year' => $year,
            ],
            ['sequence' => 0],
        );

        $sequence = PurchasingSequence::query()
            ->where('document_type', $documentType)
            ->where('year', $year)
            ->lockForUpdate()
            ->firstOrFail();

        $sequence->sequence++;
        $sequence->save();

        return $sequence->sequence;
    }

    private function incrementAtomically(PurchasingDocumentType $documentType, int $year): int
    {
        PurchasingSequence::query()->firstOrCreate(
            [
                'tenant_id' => current_tenant_id(),
                'document_type' => $documentType,
                'year' => $year,
            ],
            ['sequence' => 0],
        );

        PurchasingSequence::query()
            ->where('document_type', $documentType)
            ->where('year', $year)
            ->increment('sequence');

        return (int) PurchasingSequence::query()
            ->where('document_type', $documentType)
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
