<?php

namespace App\Presentation\Controllers;

use App\Application\Actions\Sales\ApproveCreditNoteAction;
use App\Application\Actions\Sales\CreateCreditNoteAction;
use App\Application\Services\BranchAuthorizationService;
use App\Infrastructure\Models\CreditNote;
use App\Infrastructure\Models\User;
use App\Presentation\Requests\StoreCreditNoteRequest;
use App\Presentation\Resources\CreditNoteResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class CreditNoteController extends Controller
{
    public function index(Request $request, BranchAuthorizationService $authorization): AnonymousResourceCollection
    {
        $user = $this->user($request);
        $branchIds = $authorization->authorizedBranchIds($user, 'invoice.view');
        $perPage = min(max($request->integer('per_page', 50), 1), 100);
        $query = CreditNote::query()->with('customer')->latest('id');

        if ($branchIds !== null) {
            $query->whereIn('branch_id', $branchIds);
        }

        return CreditNoteResource::collection($query->paginate($perPage));
    }

    public function store(StoreCreditNoteRequest $request, CreateCreditNoteAction $action, BranchAuthorizationService $authorization): JsonResponse
    {
        $user = $this->user($request);
        $data = $request->creditNoteData();
        $this->authorizeBranch($authorization, $user, $data->branchId);
        $creditNote = $action->handle($data, $user->getKey())->load(['customer', 'items']);

        return (new CreditNoteResource($creditNote))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function approve(Request $request, int $creditNoteId, ApproveCreditNoteAction $action, BranchAuthorizationService $authorization): CreditNoteResource
    {
        $user = $this->user($request);
        $creditNote = CreditNote::query()->findOrFail($creditNoteId);
        $this->authorizeBranch($authorization, $user, $creditNote->branch_id);

        return new CreditNoteResource(
            $action->handle($creditNote->getKey(), $user->getKey())->load(['customer', 'items']),
        );
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, Response::HTTP_UNAUTHORIZED);

        return $user;
    }

    private function authorizeBranch(BranchAuthorizationService $authorization, User $user, int $branchId): void
    {
        abort_unless($authorization->allows($user, 'invoice.void', [$branchId]), Response::HTTP_FORBIDDEN);
    }
}
