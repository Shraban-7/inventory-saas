<?php

namespace App\Presentation\Controllers;

use App\Application\Actions\Reporting\QueueProfitAndLossReportAction;
use App\Application\Services\BranchAuthorizationService;
use App\Domain\Entities\ReportJobStatus;
use App\Domain\Exceptions\ReportExpiredException;
use App\Domain\Exceptions\ReportGenerationFailedException;
use App\Domain\Exceptions\ReportNotReadyException;
use App\Infrastructure\Models\ReportJob;
use App\Infrastructure\Models\User;
use App\Presentation\Requests\QueueProfitAndLossReportRequest;
use App\Presentation\Resources\ProfitAndLossResource;
use App\Presentation\Resources\ReportJobResource;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function storeProfitAndLoss(
        QueueProfitAndLossReportRequest $request,
        QueueProfitAndLossReportAction $action,
        BranchAuthorizationService $authorization,
    ): JsonResponse {
        $user = $this->user($request);
        $data = $request->validated();
        $requestedBranchId = isset($data['branch_id']) ? (int) $data['branch_id'] : null;

        if ($requestedBranchId !== null) {
            abort_unless(
                $authorization->allows($user, 'report.view', [$requestedBranchId]),
                Response::HTTP_FORBIDDEN,
            );
            $branchIds = [$requestedBranchId];
        } else {
            $branchIds = $authorization->authorizedBranchIds($user, 'report.view');
        }

        $reportJob = $action->handle(
            new DateTimeImmutable((string) $data['start']),
            new DateTimeImmutable((string) $data['end']),
            $branchIds,
            (int) $user->getKey(),
            $requestedBranchId,
        );

        return (new ReportJobResource($reportJob))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function show(
        Request $request,
        string $reportJobId,
        BranchAuthorizationService $authorization,
    ): ReportJobResource {
        $reportJob = $this->findAuthorized(
            $request,
            $reportJobId,
            $authorization,
        );

        return new ReportJobResource($reportJob);
    }

    public function result(
        Request $request,
        string $reportJobId,
        BranchAuthorizationService $authorization,
    ): ProfitAndLossResource {
        $reportJob = $this->findAuthorized(
            $request,
            $reportJobId,
            $authorization,
        );

        match (ReportJobStatus::from((string) $reportJob->getRawOriginal('status'))) {
            ReportJobStatus::Queued,
            ReportJobStatus::Running => throw new ReportNotReadyException,
            ReportJobStatus::Failed => throw new ReportGenerationFailedException,
            ReportJobStatus::Expired => throw new ReportExpiredException,
            ReportJobStatus::Completed => null,
        };

        $result = $reportJob->getAttribute('result');

        if (! is_array($result)) {
            throw new ReportGenerationFailedException;
        }

        return new ProfitAndLossResource($result);
    }

    private function findAuthorized(
        Request $request,
        string $reportJobId,
        BranchAuthorizationService $authorization,
    ): ReportJob {
        $reportJob = ReportJob::query()->find($reportJobId);

        if (! $reportJob instanceof ReportJob) {
            throw (new ModelNotFoundException)->setModel(ReportJob::class, [$reportJobId]);
        }

        $user = $this->user($request);
        abort_unless(
            (int) $reportJob->requested_by_user_id === (int) $user->getKey()
                || $authorization->hasTenantWideRole($user, 'Admin'),
            Response::HTTP_FORBIDDEN,
        );

        return $reportJob;
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, Response::HTTP_UNAUTHORIZED);

        return $user;
    }
}
