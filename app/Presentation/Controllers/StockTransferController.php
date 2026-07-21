<?php

namespace App\Presentation\Controllers;

use App\Application\Actions\Inventory\ProcessStockTransferAction;
use App\Application\DTOs\StockTransferData;
use App\Application\Services\BranchAuthorizationService;
use App\Application\Services\CanonicalJson;
use App\Domain\Exceptions\IdempotencyConflictException;
use App\Infrastructure\Models\User;
use App\Presentation\Requests\StockTransferRequest;
use App\Presentation\Resources\StockTransferResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class StockTransferController extends Controller
{
    public function store(StockTransferRequest $request, ProcessStockTransferAction $action, BranchAuthorizationService $authorization, CanonicalJson $canonicalJson): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();
        abort_unless($user instanceof User, Response::HTTP_UNAUTHORIZED);
        $branches = [(int) $data['from_branch_id'], (int) $data['to_branch_id']];
        abort_unless($authorization->allows($user, 'stock.transfer', $branches), Response::HTTP_FORBIDDEN);

        try {
            $transfer = $action->handle(new StockTransferData(
                $branches[0],
                $branches[1],
                $request->items(),
                (string) $request->header('Idempotency-Key'),
                hash('sha256', $canonicalJson->encode($data)),
                $user->getKey(),
            ));
        } catch (IdempotencyConflictException $exception) {
            abort(Response::HTTP_CONFLICT, $exception->getMessage());
        }

        return (new StockTransferResource($transfer))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
