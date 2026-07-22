<?php

namespace App\Presentation\Controllers;

use App\Application\Services\BranchAuthorizationService;
use App\Infrastructure\Models\User;
use App\Infrastructure\Models\WebhookEndpoint;
use App\Presentation\Requests\StoreWebhookEndpointRequest;
use App\Presentation\Resources\WebhookEndpointResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class WebhookController extends Controller
{
    public function index(
        Request $request,
        BranchAuthorizationService $authorization,
    ): AnonymousResourceCollection {
        $this->authorizeAdmin($request, $authorization);

        return WebhookEndpointResource::collection(
            WebhookEndpoint::query()->where('is_active', true)->latest()->get(),
        );
    }

    public function store(StoreWebhookEndpointRequest $request): JsonResponse
    {
        $secret = Str::random(64);
        $endpoint = WebhookEndpoint::query()->create([
            ...$request->validated(),
            'secret' => $secret,
        ]);
        $data = (new WebhookEndpointResource($endpoint))->resolve($request);
        $data['secret'] = $secret;

        return response()->json(['data' => $data], Response::HTTP_CREATED);
    }

    public function destroy(
        Request $request,
        string $webhookEndpointId,
        BranchAuthorizationService $authorization,
    ): Response {
        $this->authorizeAdmin($request, $authorization);
        $endpoint = WebhookEndpoint::query()->findOrFail($webhookEndpointId);
        $endpoint->forceFill([
            'is_active' => false,
            'deactivated_at' => $endpoint->deactivated_at ?? now(),
        ])->save();

        return response()->noContent();
    }

    private function authorizeAdmin(
        Request $request,
        BranchAuthorizationService $authorization,
    ): User {
        $user = $request->user();
        abort_unless($user instanceof User, Response::HTTP_UNAUTHORIZED);
        abort_unless(
            $authorization->hasTenantWideRole($user, 'Admin'),
            Response::HTTP_FORBIDDEN,
        );

        return $user;
    }
}
