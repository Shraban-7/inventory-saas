<?php

namespace App\Application\Actions\Reporting;

use App\Application\Jobs\GenerateProfitAndLossReportJob;
use App\Application\Services\CanonicalJson;
use App\Domain\Entities\ReportJobStatus;
use App\Domain\Entities\ReportJobType;
use App\Infrastructure\Models\ReportJob;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use UnexpectedValueException;

final readonly class QueueProfitAndLossReportAction
{
    public function __construct(private CanonicalJson $canonicalJson) {}

    /**
     * @param  list<int>|null  $branchIds
     */
    public function handle(
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        ?array $branchIds,
        int $requestedByUserId,
        ?int $requestedBranchId,
    ): ReportJob {
        if ($start > $end) {
            throw new InvalidArgumentException('The report start date must not be after the end date.');
        }

        if ($branchIds !== null) {
            $branchIds = array_values(array_unique($branchIds));
            sort($branchIds, SORT_NUMERIC);
        }

        $parameters = [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
            'branch_ids' => $branchIds,
        ];
        $parameterHash = hash('sha256', $this->canonicalJson->encode($parameters));
        $ttlHours = filter_var(
            config('accounting.report_job_ttl_hours', 168),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 2160]],
        );

        if ($ttlHours === false) {
            throw new UnexpectedValueException(
                'accounting.report_job_ttl_hours must be an integer between 1 and 2160.',
            );
        }

        return DB::transaction(function () use (
            $parameters,
            $parameterHash,
            $ttlHours,
            $requestedByUserId,
            $requestedBranchId,
        ): ReportJob {
            $queuedAt = now();
            $reportJob = ReportJob::query()->create([
                'tenant_id' => current_tenant_id(),
                'requested_by_user_id' => $requestedByUserId,
                'requested_branch_id' => $requestedBranchId,
                'type' => ReportJobType::ProfitAndLoss,
                'status' => ReportJobStatus::Queued,
                'parameters' => $parameters,
                'parameter_hash' => $parameterHash,
                'queued_at' => $queuedAt,
                'expires_at' => $queuedAt->copy()->addHours($ttlHours),
            ]);

            GenerateProfitAndLossReportJob::dispatch(
                current_tenant_id(),
                (string) $reportJob->getKey(),
            )->afterCommit();

            return $reportJob;
        });
    }
}
