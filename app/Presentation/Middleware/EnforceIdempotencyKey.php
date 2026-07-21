<?php

namespace App\Presentation\Middleware;

use App\Application\Services\CanonicalJson;
use App\Infrastructure\Models\IdempotencyRequest;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnforceIdempotencyKey
{
    public function __construct(private readonly CanonicalJson $canonicalJson) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $key = $request->header('Idempotency-Key');

        if (! is_string($key) || ! Str::isUuid($key)) {
            return $this->problem(
                $request,
                Response::HTTP_BAD_REQUEST,
                'Invalid Idempotency-Key',
                'A valid UUID Idempotency-Key header is required.',
            );
        }

        $body = $request->isJson() ? $request->json()->all() : $request->request->all();
        $payloadHash = hash('sha256', $key.$this->canonicalJson->encode($body));
        $lock = Cache::lock('idempotency:'.current_tenant_id().":{$key}", 30);

        try {
            return $lock->block(5, function () use ($key, $next, $payloadHash, $request): Response {
                $storedRequest = IdempotencyRequest::query()
                    ->where('key', $key)
                    ->first();

                if ($storedRequest !== null && $storedRequest->expires_at->isPast()) {
                    $storedRequest->delete();
                    $storedRequest = null;
                }

                if ($storedRequest !== null) {
                    if (! hash_equals($storedRequest->payload_hash, $payloadHash)) {
                        return $this->problem(
                            $request,
                            Response::HTTP_CONFLICT,
                            'Idempotency conflict',
                            'This Idempotency-Key was already used with a different payload.',
                        );
                    }

                    return response(
                        (string) $storedRequest->response_body['content'],
                        $storedRequest->response_status,
                        ['Content-Type' => 'application/json'],
                    );
                }

                $response = $next($request);

                IdempotencyRequest::query()->create([
                    'key' => $key,
                    'payload_hash' => $payloadHash,
                    'response_body' => ['content' => $response->getContent()],
                    'response_status' => $response->getStatusCode(),
                    'expires_at' => now()->addDay(),
                ]);

                return $response;
            });
        } catch (LockTimeoutException) {
            return $this->problem(
                $request,
                Response::HTTP_CONFLICT,
                'Request already in progress',
                'Another request with this Idempotency-Key is still being processed.',
            );
        }
    }

    private function problem(Request $request, int $status, string $title, string $detail): Response
    {
        return response()->json([
            'type' => 'about:blank',
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
            'instance' => '/'.$request->path(),
            'errors' => [],
        ], $status, ['Content-Type' => 'application/problem+json']);
    }
}
