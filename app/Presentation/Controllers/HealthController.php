<?php

namespace App\Presentation\Controllers;

use App\Application\Services\Health\ReadinessProbe;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class HealthController
{
    public function live(): JsonResponse
    {
        return response()->json(['status' => 'ok'], Response::HTTP_OK);
    }

    public function ready(ReadinessProbe $probe): JsonResponse
    {
        $result = $probe->check();
        $status = $result['status'] === 'ok' ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE;

        return response()->json([
            'status' => $result['status'],
            'components' => $result['components'],
        ], $status);
    }
}
