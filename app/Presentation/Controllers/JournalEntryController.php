<?php

namespace App\Presentation\Controllers;

use App\Application\Actions\Accounting\CreateManualJournalAction;
use App\Application\Services\BranchAuthorizationService;
use App\Domain\Repositories\JournalHistoryRepository;
use App\Infrastructure\Models\JournalEntry;
use App\Infrastructure\Models\User;
use App\Presentation\Requests\ListJournalEntriesRequest;
use App\Presentation\Requests\StoreManualJournalRequest;
use App\Presentation\Resources\JournalEntryDetailResource;
use App\Presentation\Resources\JournalEntryResource;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class JournalEntryController extends Controller
{
    public function index(
        ListJournalEntriesRequest $request,
        JournalHistoryRepository $journals,
        BranchAuthorizationService $authorization,
    ): AnonymousResourceCollection {
        $data = $request->validated();
        $branchIds = $authorization->authorizedBranchIds($this->user($request), 'report.view');

        return JournalEntryResource::collection($journals->paginate(
            $branchIds,
            isset($data['date_from']) ? (string) $data['date_from'] : null,
            isset($data['date_to']) ? (string) $data['date_to'] : null,
            isset($data['reference_type']) ? (string) $data['reference_type'] : null,
            isset($data['per_page']) ? (int) $data['per_page'] : 50,
        ));
    }

    public function show(
        Request $request,
        int $journalEntryId,
        JournalHistoryRepository $journals,
        BranchAuthorizationService $authorization,
    ): JournalEntryDetailResource {
        $entry = $journals->find($journalEntryId);

        if (! $entry instanceof JournalEntry) {
            throw (new ModelNotFoundException)->setModel(JournalEntry::class, [$journalEntryId]);
        }

        abort_unless(
            $authorization->allows($this->user($request), 'report.view', [(int) $entry->branch_id]),
            Response::HTTP_FORBIDDEN,
        );

        return new JournalEntryDetailResource($entry);
    }

    public function store(
        StoreManualJournalRequest $request,
        CreateManualJournalAction $action,
        BranchAuthorizationService $authorization,
    ): JsonResponse {
        $user = $this->user($request);
        $data = $request->journalData();

        abort_unless(
            $authorization->allowsRoleOnBranch($user, 'Accountant', $data->branchId),
            Response::HTTP_FORBIDDEN,
        );

        $entry = $action->handle($data, $user->getKey())
            ->load(['branch', 'lines.account']);

        return (new JournalEntryDetailResource($entry))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, Response::HTTP_UNAUTHORIZED);

        return $user;
    }
}
