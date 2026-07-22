<?php

namespace App\Application\Listeners;

use App\Application\Services\Health\QueueHeartbeatService;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Throwable;

final class RecordQueueHeartbeat
{
    public function __construct(
        private readonly QueueHeartbeatService $heartbeats,
    ) {}

    public function handle(object $event): void
    {
        try {
            match (true) {
                $event instanceof Looping => $this->onLooping($event),
                $event instanceof JobProcessing => $this->onProcessing($event),
                $event instanceof JobProcessed,
                $event instanceof JobExceptionOccurred => $this->onFinished($event),
                default => null,
            };
        } catch (Throwable) {
            // Telemetry must never crash workers.
        }
    }

    private function onLooping(Looping $event): void
    {
        foreach ($this->queuesFrom($event->queue) as $queue) {
            $this->heartbeats->touchIdle($queue);
        }
    }

    private function onProcessing(JobProcessing $event): void
    {
        $queue = $event->job->getQueue() ?: 'default';
        $this->heartbeats->touchBusy($queue, $this->resolveTimeout($event));
    }

    private function onFinished(JobProcessed|JobExceptionOccurred $event): void
    {
        $queue = $event->job->getQueue() ?: 'default';
        $this->heartbeats->touchIdle($queue);
    }

    /**
     * @return list<string>
     */
    private function queuesFrom(string $queue): array
    {
        return array_values(array_filter(array_map(
            static fn (string $name): string => trim($name),
            explode(',', $queue),
        )));
    }

    private function resolveTimeout(JobProcessing $event): int
    {
        $timeout = $event->job->timeout();

        if ($timeout !== null && $timeout > 0) {
            return $timeout;
        }

        $payload = $event->job->payload();
        $command = $payload['data']['command'] ?? null;

        if (is_string($command)) {
            try {
                $instance = unserialize($command);
                if (is_object($instance) && isset($instance->timeout) && is_int($instance->timeout) && $instance->timeout > 0) {
                    return $instance->timeout;
                }
            } catch (Throwable) {
                // Fall through to default.
            }
        }

        return (int) config('health.heartbeat.default_worker_timeout', 90);
    }
}
