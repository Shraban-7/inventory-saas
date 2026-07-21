<?php

namespace App\Presentation\Controllers;

use App\Application\Actions\Purchasing\CancelPurchaseOrderAction;
use App\Application\Actions\Purchasing\ConfirmPurchaseOrderAction;
use App\Application\Actions\Purchasing\CreatePurchaseOrderAction;
use App\Application\Services\BranchAuthorizationService;
use App\Infrastructure\Models\PurchaseOrder;
use App\Infrastructure\Models\User;
use App\Presentation\Requests\ListPurchaseOrdersRequest;
use App\Presentation\Requests\StorePurchaseOrderRequest;
use App\Presentation\Resources\PurchaseOrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class PurchaseOrderController extends Controller
{
    public function index(ListPurchaseOrdersRequest $request, BranchAuthorizationService $authorization): AnonymousResourceCollection
    {
        $user = $this->user($request);
        $data = $request->validated();
        $branchIds = $authorization->authorizedBranchIds($user, 'purchase.create');
        $query = PurchaseOrder::query()->with(['supplier', 'items']);

        if ($branchIds !== null) {
            $query->whereIn('branch_id', $branchIds);
        }
        if (isset($data['status'])) {
            $query->where('status', (string) $data['status']);
        }
        if (isset($data['supplier_id'])) {
            $query->where('supplier_id', (int) $data['supplier_id']);
        }

        return PurchaseOrderResource::collection(
            $query->latest('id')->paginate(isset($data['per_page']) ? (int) $data['per_page'] : 50),
        );
    }

    public function store(StorePurchaseOrderRequest $request, CreatePurchaseOrderAction $action, BranchAuthorizationService $authorization): JsonResponse
    {
        $user = $this->user($request);
        $data = $request->purchaseOrderData();
        $this->authorizeBranch($authorization, $user, $data->branchId);
        $order = $action->handle($data, (int) $user->getKey())->load(['supplier', 'items']);

        return (new PurchaseOrderResource($order))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function confirm(Request $request, int $purchaseOrderId, ConfirmPurchaseOrderAction $action, BranchAuthorizationService $authorization): PurchaseOrderResource
    {
        $user = $this->user($request);
        $order = PurchaseOrder::query()->findOrFail($purchaseOrderId);
        $this->authorizeBranch($authorization, $user, $order->branch_id);

        return new PurchaseOrderResource($action->handle($purchaseOrderId, (int) $user->getKey())->load(['supplier', 'items']));
    }

    public function cancel(Request $request, int $purchaseOrderId, CancelPurchaseOrderAction $action, BranchAuthorizationService $authorization): PurchaseOrderResource
    {
        $user = $this->user($request);
        $order = PurchaseOrder::query()->findOrFail($purchaseOrderId);
        $this->authorizeBranch($authorization, $user, $order->branch_id);

        return new PurchaseOrderResource($action->handle($purchaseOrderId, (int) $user->getKey())->load(['supplier', 'items']));
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, Response::HTTP_UNAUTHORIZED);

        return $user;
    }

    private function authorizeBranch(BranchAuthorizationService $authorization, User $user, int $branchId): void
    {
        abort_unless($authorization->allows($user, 'purchase.create', [$branchId]), Response::HTTP_FORBIDDEN);
    }
}
