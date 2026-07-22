<?php

namespace App\Presentation\Controllers;

use App\Application\Actions\Inventory\QueueBulkImportAction;
use App\Application\Services\BranchAuthorizationService;
use App\Domain\Entities\BulkImportRowStatus;
use App\Domain\Entities\BulkImportType;
use App\Infrastructure\Models\BulkImport;
use App\Infrastructure\Models\User;
use App\Presentation\Requests\BulkImportUploadRequest;
use App\Presentation\Resources\BulkImportResource;
use App\Presentation\Resources\BulkImportRowErrorResource;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class BulkImportController extends Controller
{
    public function products(BulkImportUploadRequest $request, QueueBulkImportAction $action): JsonResponse
    {
        return $this->queue($request, $action, BulkImportType::Products);
    }

    public function stockAdjustments(BulkImportUploadRequest $request, QueueBulkImportAction $action): JsonResponse
    {
        return $this->queue($request, $action, BulkImportType::StockAdjustments);
    }

    public function show(
        Request $request,
        string $bulkImportId,
        BranchAuthorizationService $authorization,
    ): BulkImportResource {
        return new BulkImportResource($this->findAuthorized($request, $bulkImportId, $authorization));
    }

    public function errors(
        Request $request,
        string $bulkImportId,
        BranchAuthorizationService $authorization,
    ): AnonymousResourceCollection {
        $import = $this->findAuthorized($request, $bulkImportId, $authorization);
        $errors = $import->rows()
            ->where('status', BulkImportRowStatus::Failed)
            ->orderBy('row_number')
            ->paginate(100);

        return BulkImportRowErrorResource::collection($errors);
    }

    private function queue(
        BulkImportUploadRequest $request,
        QueueBulkImportAction $action,
        BulkImportType $type,
    ): JsonResponse {
        $user = $this->user($request);
        $import = $action->handle($request->csv(), $type, (int) $user->getKey());

        return (new BulkImportResource($import))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    private function findAuthorized(
        Request $request,
        string $bulkImportId,
        BranchAuthorizationService $authorization,
    ): BulkImport {
        $import = BulkImport::query()->find($bulkImportId);

        if (! $import instanceof BulkImport) {
            throw (new ModelNotFoundException)->setModel(BulkImport::class, [$bulkImportId]);
        }

        $user = $this->user($request);
        abort_unless(
            (int) $import->requested_by_user_id === (int) $user->getKey()
                || $authorization->hasTenantWideRole($user, 'Admin'),
            Response::HTTP_FORBIDDEN,
        );

        return $import;
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, Response::HTTP_UNAUTHORIZED);

        return $user;
    }
}
