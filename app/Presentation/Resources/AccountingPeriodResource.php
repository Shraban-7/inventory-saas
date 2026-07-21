<?php

namespace App\Presentation\Resources;

use App\Infrastructure\Models\AccountingPeriod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountingPeriodResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $period = $this->resource;

        if (! $period instanceof AccountingPeriod) {
            return [];
        }

        return [
            'id' => $period->getKey(),
            'year' => $period->year,
            'month' => $period->month,
            'is_locked' => $period->is_locked,
            'locked_at' => $period->locked_at,
            'locked_by_user_id' => $period->locked_by_user_id,
            'created_at' => $period->created_at,
            'updated_at' => $period->updated_at,
        ];
    }
}
