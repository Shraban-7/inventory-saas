<?php

namespace App\Infrastructure\Logging;

use Illuminate\Log\Logger;
use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Processor\PsrLogMessageProcessor;

final class ConfigureObservabilityLogger
{
    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();

        if (! method_exists($monolog, 'getHandlers')) {
            return;
        }

        $isLocal = app()->environment('local');

        foreach ($monolog->getHandlers() as $handler) {
            if ($isLocal) {
                $handler->setFormatter(new LineFormatter(
                    "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
                    'Y-m-d H:i:s',
                    true,
                    true,
                ));
            } else {
                $handler->setFormatter(new JsonFormatter(JsonFormatter::BATCH_MODE_JSON, true));
            }
        }

        if (method_exists($monolog, 'pushProcessor')) {
            $monolog->pushProcessor(new PsrLogMessageProcessor);
            $monolog->pushProcessor(new EnsureObservabilityContextProcessor);
        }
    }
}
