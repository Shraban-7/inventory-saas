<?php

namespace App\Application\Services\Health;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

final class ReadinessProbe
{
    public function __construct(
        private readonly QueueHeartbeatService $heartbeats,
    ) {}

    /**
     * @return array{status: string, components: array<string, string>}
     */
    public function check(): array
    {
        $components = [
            'database' => $this->databaseStatus(),
            'redis' => $this->redisStatus(),
        ];

        foreach ((array) config('health.queues', []) as $queue) {
            $components['queue:'.$queue] = $this->heartbeats->isFresh((string) $queue) ? 'ok' : 'fail';
        }

        $healthy = ! in_array('fail', array_values($components), true);

        return [
            'status' => $healthy ? 'ok' : 'fail',
            'components' => $components,
        ];
    }

    private function databaseStatus(): string
    {
        try {
            DB::select('select 1');

            return 'ok';
        } catch (Throwable) {
            return 'fail';
        }
    }

    private function redisStatus(): string
    {
        try {
            $pong = Redis::connection()->ping();

            if ($pong === true || $pong === 'PONG' || $pong === '+PONG') {
                return 'ok';
            }

            return 'fail';
        } catch (Throwable) {
            return 'fail';
        }
    }
}
