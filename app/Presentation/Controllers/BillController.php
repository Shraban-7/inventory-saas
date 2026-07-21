<?php

namespace App\Presentation\Controllers;

use App\Application\Actions\Purchasing\ApproveBillAction;
use App\Application\Actions\Purchasing\CreateBillAction;
use App\Application\Actions\Purchasing\RecordBillPaymentAction;
use App\Application\Services\BranchAuthorizationService;
use App\Infrastructure\Models\Bill;
use App\Infrastructure\Models\GoodsReceiptNote;
use App\Infrastructure\Models\User;
use App\Presentation\Requests\ListBillsRequest;
use App\Presentation\Requests\StoreBillPaymentRequest;
use App\Presentation\Requests\StoreBillRequest;
use App\Presentation\Resources\BillPaymentResource;
use App\Presentation\Resources\BillResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class BillController extends Controller
{
    public function index(ListBillsRequest $request, BranchAuthorizationService $authorization): AnonymousResourceCollection
    {
        $user = $this->user($request);
        $data = $request->validated();
        $branchIds = $authorization->authorizedBranchIds($user, 'purchase.create');
        $query = Bill::query()->with(['supplier', 'goodsReceiptNote', 'items', 'payments']);

        if ($branchIds !== null) {
            $query->whereIn('branch_id', $branchIds);
        }
        if (isset($data['status'])) {
            $query->where('status', (string) $data['status']);
        }
        if (isset($data['supplier_id'])) {
            $query->where('supplier_id', (int) $data['supplier_id']);
        }

        return BillResource::collection(
            $query->latest('id')->paginate(isset($data['per_page']) ? (int) $data['per_page'] : 50),
        );
    }

    public function store(StoreBillRequest $request, CreateBillAction $action, BranchAuthorizationService $authorization): JsonResponse
    {
        $user = $this->user($request);
        $data = $request->billData();
        $this->authorizeBranch($authorization, $user, $data->branchId);

        if ($data->grnId !== null) {
            $grn = GoodsReceiptNote::query()->findOrFail($data->grnId);
            $this->authorizeBranch($authorization, $user, $grn->branch_id);
        }

        $bill = $action->handle($data, (int) $user->getKey())
            ->load(['supplier', 'goodsReceiptNote', 'items', 'payments']);

        return (new BillResource($bill))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function approve(Request $request, int $billId, ApproveBillAction $action, BranchAuthorizationService $authorization): BillResource
    {
        $user = $this->user($request);
        $bill = Bill::query()->findOrFail($billId);
        $this->authorizeBranch($authorization, $user, $bill->branch_id);

        return new BillResource(
            $action->handle($billId, (int) $user->getKey())
                ->load(['supplier', 'goodsReceiptNote', 'items', 'payments']),
        );
    }

    public function payment(
        StoreBillPaymentRequest $request,
        int $billId,
        RecordBillPaymentAction $action,
        BranchAuthorizationService $authorization,
    ): JsonResponse {
        $user = $this->user($request);
        $bill = Bill::query()->findOrFail($billId);
        $this->authorizeBranch($authorization, $user, $bill->branch_id);
        $payment = $action->handle($billId, $request->billPaymentData(), (int) $user->getKey());

        return (new BillPaymentResource($payment))->response()->setStatusCode(Response::HTTP_CREATED);
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
