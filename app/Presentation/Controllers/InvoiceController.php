<?php

namespace App\Presentation\Controllers;

use App\Application\Actions\Sales\CreateInvoiceAction;
use App\Application\Actions\Sales\RecordReceiptAction;
use App\Application\Actions\Sales\VoidInvoiceAction;
use App\Application\Services\BranchAuthorizationService;
use App\Infrastructure\Models\Invoice;
use App\Infrastructure\Models\User;
use App\Presentation\Requests\ListInvoicesRequest;
use App\Presentation\Requests\StoreInvoiceRequest;
use App\Presentation\Requests\StoreReceiptRequest;
use App\Presentation\Resources\InvoiceResource;
use App\Presentation\Resources\ReceiptResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    public function index(ListInvoicesRequest $request, BranchAuthorizationService $authorization): AnonymousResourceCollection
    {
        $user = $this->user($request);
        $data = $request->validated();
        $branchIds = $authorization->authorizedBranchIds($user, 'invoice.view');
        $perPage = isset($data['per_page']) ? (int) $data['per_page'] : 50;
        $query = Invoice::query()->with('customer');

        if ($branchIds !== null) {
            $query->whereIn('branch_id', $branchIds);
        }
        if (isset($data['status'])) {
            $query->where('status', (string) $data['status']);
        }
        if (isset($data['date_from'])) {
            $query->whereDate('invoice_date', '>=', (string) $data['date_from']);
        }
        if (isset($data['date_to'])) {
            $query->whereDate('invoice_date', '<=', (string) $data['date_to']);
        }
        if (isset($data['customer_id'])) {
            $query->where('customer_id', (int) $data['customer_id']);
        }

        return InvoiceResource::collection(
            $query->orderByDesc('id')->cursorPaginate($perPage),
        );
    }

    public function store(StoreInvoiceRequest $request, CreateInvoiceAction $action, BranchAuthorizationService $authorization): JsonResponse
    {
        $user = $this->user($request);
        $data = $request->invoiceData();
        $this->authorizeBranch($authorization, $user, 'invoice.create', $data->branchId);
        $invoice = $action->handle($data, $user->getKey())->load(['customer', 'items', 'receipts']);

        return (new InvoiceResource($invoice))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, int $invoiceId, BranchAuthorizationService $authorization): InvoiceResource
    {
        $user = $this->user($request);
        $invoice = Invoice::query()->with(['customer', 'items', 'receipts'])->findOrFail($invoiceId);
        $this->authorizeBranch($authorization, $user, 'invoice.view', $invoice->branch_id);

        return new InvoiceResource($invoice);
    }

    public function void(Request $request, int $invoiceId, VoidInvoiceAction $action, BranchAuthorizationService $authorization): InvoiceResource
    {
        $user = $this->user($request);
        $invoice = Invoice::query()->findOrFail($invoiceId);
        $this->authorizeBranch($authorization, $user, 'invoice.void', $invoice->branch_id);

        return new InvoiceResource(
            $action->handle($invoice->getKey(), $user->getKey())->load(['customer', 'items', 'receipts']),
        );
    }

    public function receipt(StoreReceiptRequest $request, int $invoiceId, RecordReceiptAction $action, BranchAuthorizationService $authorization): JsonResponse
    {
        $user = $this->user($request);
        $invoice = Invoice::query()->findOrFail($invoiceId);
        $this->authorizeBranch($authorization, $user, 'invoice.create', $invoice->branch_id);
        $receipt = $action->handle($request->receiptData(), $user->getKey());

        return (new ReceiptResource($receipt))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, Response::HTTP_UNAUTHORIZED);

        return $user;
    }

    private function authorizeBranch(BranchAuthorizationService $authorization, User $user, string $permission, int $branchId): void
    {
        abort_unless($authorization->allows($user, $permission, [$branchId]), Response::HTTP_FORBIDDEN);
    }
}
