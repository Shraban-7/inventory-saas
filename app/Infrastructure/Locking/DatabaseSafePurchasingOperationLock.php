<?php

namespace App\Infrastructure\Locking;

use App\Application\Contracts\PurchasingOperationLock;
use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class DatabaseSafePurchasingOperationLock implements PurchasingOperationLock
{
    public function run(string $operation, string $key, Closure $callback): mixed
    {
        $identity = current_tenant_id().':'.$operation.':'.$key;
        $hash = hash('sha256', $identity);
        $connection = DB::connection();

        if (in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            return $this->withDatabaseLock($connection, 'purchase-op:'.mb_substr($hash, 0, 48), $callback);
        }

        return Cache::lock('purchase-op:'.$hash, 60)->block(10, $callback);
    }

    private function withDatabaseLock(ConnectionInterface $connection, string $name, Closure $callback): mixed
    {
        $result = $connection->selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$name]);

        if ((int) ($result->acquired ?? 0) !== 1) {
            throw new RuntimeException('Could not acquire the purchasing operation lock.');
        }

        $callbackException = null;

        try {
            return $callback();
        } catch (Throwable $exception) {
            $callbackException = $exception;

            throw $exception;
        } finally {
            try {
                $released = $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [$name]);

                if ((int) ($released->released ?? 0) !== 1 && $callbackException === null) {
                    throw new RuntimeException('Could not release the purchasing operation lock.');
                }
            } catch (Throwable $releaseException) {
                if ($callbackException === null) {
                    throw $releaseException;
                }
            }
        }
    }
}
