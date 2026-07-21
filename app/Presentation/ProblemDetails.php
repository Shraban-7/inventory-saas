<?php

namespace App\Presentation;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProblemDetails
{
    /** @param array<string, mixed> $errors */
    public static function response(
        Request $request,
        int $status,
        string $title,
        string $detail,
        array $errors = [],
        string $type = 'about:blank',
    ): JsonResponse {
        return response()->json([
            'type' => $type,
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
            'instance' => '/'.$request->path(),
            'errors' => $errors,
        ], $status, ['Content-Type' => 'application/problem+json']);
    }
}
