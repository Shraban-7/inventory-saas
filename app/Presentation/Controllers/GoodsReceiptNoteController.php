<?php

namespace App\Presentation\Controllers;

use App\Application\Actions\Purchasing\ProcessGoodsReceiptAction;
use App\Application\Services\BranchAuthorizationService;
use App\Application\Services\CanonicalJson;
use App\Infrastructure\Models\GoodsReceiptNote;
use App\Infrastructure\Models\PurchaseOrder;
use App\Infrastructure\Models\User;
use App\Presentation\Requests\ListGoodsReceiptNotesRequest;
use App\Presentation\Requests\StoreGoodsReceiptNoteRequest;
use App\Presentation\Resources\GoodsReceiptNoteResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class GoodsReceiptNoteController extends Controller
{
    public function index(ListGoodsReceiptNotesRequest $request, BranchAuthorizationService $authorization): AnonymousResourceCollection
    {
        $user = $this->user($request);
        $data = $request->validated();
        $branchIds = $authorization->authorizedBranchIds($user, 'purchase.receive');
        $query = GoodsReceiptNote::query()->with(['supplier', 'purchaseOrder', 'items']);

        if ($branchIds !== null) {
            $query->whereIn('branch_id', $branchIds);
        }
        if (isset($data['supplier_id'])) {
            $query->where('supplier_id', (int) $data['supplier_id']);
        }

        return GoodsReceiptNoteResource::collection(
            $query->latest('id')->paginate(isset($data['per_page']) ? (int) $data['per_page'] : 50),
        );
    }

    public function store(
        StoreGoodsReceiptNoteRequest $request,
        ProcessGoodsReceiptAction $action,
        BranchAuthorizationService $authorization,
        CanonicalJson $canonicalJson,
    ): JsonResponse {
        $user = $this->user($request);
        $data = $request->goodsReceiptData($canonicalJson);
        $this->authorizeBranch($authorization, $user, $data->branchId);

        if ($data->purchaseOrderId !== null) {
            $order = PurchaseOrder::query()->findOrFail($data->purchaseOrderId);
            $this->authorizeBranch($authorization, $user, $order->branch_id);
        }

        $grn = $action->handle($data, (int) $user->getKey())->load(['supplier', 'purchaseOrder', 'items']);

        return (new GoodsReceiptNoteResource($grn))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, Response::HTTP_UNAUTHORIZED);

        return $user;
    }

    private function authorizeBranch(BranchAuthorizationService $authorization, User $user, int $branchId): void
    {
        abort_unless($authorization->allows($user, 'purchase.receive', [$branchId]), Response::HTTP_FORBIDDEN);
    }
}
