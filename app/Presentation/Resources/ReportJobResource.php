<?php

namespace App\Presentation\Resources;

use App\Domain\Entities\ReportJobStatus;
use App\Infrastructure\Models\ReportJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportJobResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $reportJob = $this->resource;

        if (! $reportJob instanceof ReportJob) {
            return [];
        }

        $status = (string) $reportJob->getRawOriginal('status');

        return [
            'id' => $reportJob->getKey(),
            'type' => (string) $reportJob->getRawOriginal('type'),
            'status' => $status,
            'parameters' => $reportJob->getAttribute('parameters'),
            'timestamps' => [
                'queued_at' => $reportJob->queued_at,
                'started_at' => $reportJob->started_at,
                'completed_at' => $reportJob->completed_at,
                'expires_at' => $reportJob->expires_at,
                'created_at' => $reportJob->created_at,
                'updated_at' => $reportJob->updated_at,
            ],
            'result_url' => $this->when(
                $status === ReportJobStatus::Completed->value,
                fn (): string => route(
                    'reports.jobs.result',
                    ['reportJobId' => $reportJob->getKey()],
                ),
            ),
            'error' => $this->when(
                $status === ReportJobStatus::Failed->value,
                [
                    'code' => $reportJob->error_code,
                    'message' => $reportJob->error_message,
                ],
            ),
        ];
    }
}
