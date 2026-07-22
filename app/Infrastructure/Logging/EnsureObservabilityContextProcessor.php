<?php

namespace App\Infrastructure\Logging;

use Illuminate\Support\Facades\Context;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

final class EnsureObservabilityContextProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        if (app()->environment('local')) {
            return $record;
        }

        $context = $record->context;
        $context['request_id'] = Context::get('request_id');
        $context['tenant_id'] = Context::get('tenant_id');
        $context['user_id'] = Context::get('user_id');

        return $record->with(context: $context);
    }
}
