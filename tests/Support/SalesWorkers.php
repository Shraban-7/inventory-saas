<?php

namespace Tests\Support;

use RuntimeException;
use Symfony\Component\Process\Process;

final class SalesWorkers
{
    /**
     * @param  list<array<string, mixed>>  $payloads
     * @return list<array<string, mixed>>
     */
    public static function run(string $mode, array $payloads): array
    {
        return self::runMixed(array_map(
            static fn (array $payload): array => [
                'mode' => $mode,
                'payload' => $payload,
            ],
            $payloads,
        ));
    }

    /**
     * @param  list<array{mode: string, payload: array<string, mixed>}>  $workers
     * @return list<array<string, mixed>>
     */
    public static function runMixed(array $workers): array
    {
        $barrier = sys_get_temp_dir().DIRECTORY_SEPARATOR.'inventory-sales-'.bin2hex(random_bytes(8)).'.barrier';
        $processes = [];
        $readyFiles = [];

        foreach ($workers as $index => $worker) {
            $workerToken = (string) $index;
            $readyFiles[] = $barrier.'.'.$workerToken.'.ready';
            $process = new Process([
                PHP_BINARY,
                base_path('tests/Fixtures/Concurrency/sales-worker.php'),
                $worker['mode'],
            ]);
            $process->setInput(json_encode([
                ...$worker['payload'],
                'barrier' => $barrier,
                'worker_token' => $workerToken,
            ], JSON_THROW_ON_ERROR));
            $process->setTimeout(60);
            $process->start();
            $processes[] = $process;
        }

        $results = [];

        try {
            self::awaitReadyWorkers($processes, $readyFiles);

            if (file_put_contents($barrier, 'go', LOCK_EX) === false) {
                throw new RuntimeException('Concurrency runner could not release the barrier.');
            }

            foreach ($processes as $process) {
                $process->wait();
                $output = trim($process->getOutput());
                $decoded = json_decode($output, true);

                if (! is_array($decoded)) {
                    throw new RuntimeException('Worker returned invalid JSON: '.$output.' '.$process->getErrorOutput());
                }

                $decoded['exit_code'] = $process->getExitCode();
                $results[] = $decoded;
            }
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(1);
                }
            }

            if (is_file($barrier)) {
                unlink($barrier);
            }

            foreach ($readyFiles as $readyFile) {
                if (is_file($readyFile)) {
                    unlink($readyFile);
                }
            }
        }

        return $results;
    }

    /**
     * @param  list<Process>  $processes
     * @param  list<string>  $readyFiles
     */
    private static function awaitReadyWorkers(array $processes, array $readyFiles): void
    {
        $deadline = microtime(true) + 20;

        while (count(array_filter($readyFiles, 'is_file')) !== count($readyFiles)) {
            foreach ($processes as $process) {
                if (! $process->isRunning()) {
                    throw new RuntimeException(
                        'Concurrency worker exited before the barrier: '.
                        trim($process->getOutput().' '.$process->getErrorOutput()),
                    );
                }
            }

            if (microtime(true) >= $deadline) {
                throw new RuntimeException('Concurrency workers did not reach the barrier in time.');
            }

            usleep(10_000);
        }
    }
}
