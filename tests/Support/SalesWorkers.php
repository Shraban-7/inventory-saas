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
        $barrier = sys_get_temp_dir().DIRECTORY_SEPARATOR.'inventory-sales-'.bin2hex(random_bytes(8)).'.barrier';
        $processes = [];

        foreach ($payloads as $payload) {
            $process = new Process([PHP_BINARY, base_path('tests/Fixtures/Concurrency/sales-worker.php'), $mode]);
            $process->setInput(json_encode([...$payload, 'barrier' => $barrier], JSON_THROW_ON_ERROR));
            $process->setTimeout(60);
            $process->start();
            $processes[] = $process;
        }

        usleep(100_000);
        file_put_contents($barrier, 'go', LOCK_EX);
        $results = [];

        try {
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
            if (is_file($barrier)) {
                unlink($barrier);
            }
        }

        return $results;
    }
}
