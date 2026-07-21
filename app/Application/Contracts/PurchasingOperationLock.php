<?php

namespace App\Application\Contracts;

use Closure;

interface PurchasingOperationLock
{
    /** @template T
     * @param  Closure(): T  $callback
     * @return T
     */
    public function run(string $operation, string $key, Closure $callback): mixed;
}
