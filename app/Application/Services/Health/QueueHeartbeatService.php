<?php

namespace App\Application\Services\Health;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class QueueHeartbeatService
{
    public function touchIdle(string $queue): void
    {
        $this->safeWrite($queue, 'idle', (int) config('health.heartbeat.idle_ttl_seconds', 45));
    }

    public function touchBusy(string $queue, ?int $workerTimeout = null): void
    {
        $timeout = $workerTimeout ?? (int) config('health.heartbeat.default_worker_timeout', 90);
        $grace = (int) config('health.heartbeat.busy_grace_seconds', 30);
        $this->safeWrite($queue, 'busy', max(1, $timeout + $grace));
    }

    /**
     * @return array{queue: string, status: string}|null
     */
    public function status(string $queue): ?array
    {
        try {
            $payload = $this->store()->get($this->key($queue));
        } catch (Throwable) {
            return null;
        }

        if (! is_array($payload) || ! isset($payload['state'])) {
            return null;
        }

        return [
            'queue' => $queue,
            'status' => (string) $payload['state'],
        ];
    }

    public function isFresh(string $queue): bool
    {
        return $this->status($queue) !== null;
    }

    private function safeWrite(string $queue, string $state, int $ttl): void
    {
        try {
            $key = $this->key($queue);
            $throttleKey = $key.':throttle:'.$state;
            $throttleSeconds = max(1, (int) config('health.heartbeat.throttle_seconds', 5));
            $store = $this->store();

            if ($store->has($throttleKey) && $state === 'idle') {
                return;
            }

            $store->put($key, [
                'state' => $state,
                'updated_at' => now()->toIso8601String(),
            ], $ttl);

            $store->put($throttleKey, true, $throttleSeconds);
        } catch (Throwable) {
            // Telemetry must never crash workers.
        }
    }

    private function key(string $queue): string
    {
        return (string) config('health.heartbeat.key_prefix', 'queue:heartbeat:').$queue;
    }

    private function store(): Repository
    {
        $store = config('health.heartbeat.cache_store');

        return $store ? Cache::store($store) : Cache::store();
    }
}
