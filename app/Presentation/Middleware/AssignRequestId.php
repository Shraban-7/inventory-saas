<?php

namespace App\Presentation\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->headers->get('X-Request-ID');
        $requestId = is_string($incoming) && Str::isUuid($incoming)
            ? $incoming
            : (string) Str::uuid();

        $request->headers->set('X-Request-ID', $requestId);
        $request->attributes->set('request_id', $requestId);
        Context::add('request_id', $requestId);

        $response = $next($request);
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }

    /**
     * Clear request context immediately after each response lifecycle.
     * Exception reporting runs during handle()/pipeline before this is invoked.
     */
    public function terminate(Request $request, Response $response): void
    {
        $requestId = $request->attributes->get('request_id');

        if (is_string($requestId) && Context::get('request_id') === $requestId) {
            Context::forget('request_id');
        } elseif ($requestId === null) {
            Context::forget('request_id');
        }
    }
}
