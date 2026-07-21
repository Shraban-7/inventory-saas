<?php

namespace App\Presentation\Controllers;

use App\Application\Actions\Inventory\AdjustStockAction;
use App\Application\DTOs\StockAdjustmentData;
use App\Application\Services\BranchAuthorizationService;
use App\Application\Services\CanonicalJson;
use App\Domain\Entities\Quantity;
use App\Domain\Entities\StockMovementType;
use App\Domain\Exceptions\IdempotencyConflictException;
use App\Infrastructure\Models\User;
use App\Presentation\Requests\StockAdjustmentRequest;
use App\Presentation\Resources\StockAdjustmentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class StockAdjustmentController extends Controller
{
    public function store(StockAdjustmentRequest $request, AdjustStockAction $action, BranchAuthorizationService $authorization, CanonicalJson $canonicalJson): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();
        abort_unless($user instanceof User, Response::HTTP_UNAUTHORIZED);
        abort_unless($authorization->allows($user, 'stock.adjust', [(int) $data['branch_id']]), Response::HTTP_FORBIDDEN);

        $type = StockMovementType::from($data['type']);
        $delta = (string) $data['quantity_delta'];

        if (Quantity::from($delta)->isPositive() !== ($type === StockMovementType::StockAdjustmentIn)) {
            throw ValidationException::withMessages(['type' => 'The adjustment type must match the quantity direction.']);
        }

        try {
            $adjustment = $action->handle(new StockAdjustmentData(
                (int) $data['variant_id'],
                (int) $data['branch_id'],
                $delta,
                $data['reason'],
                $type,
                (string) $request->header('Idempotency-Key'),
                hash('sha256', $canonicalJson->encode($data)),
                $user->getKey(),
            ));
        } catch (IdempotencyConflictException $exception) {
            abort(Response::HTTP_CONFLICT, $exception->getMessage());
        }

        return (new StockAdjustmentResource($adjustment))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
