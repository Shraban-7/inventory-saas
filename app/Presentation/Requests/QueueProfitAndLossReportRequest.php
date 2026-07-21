<?php

namespace App\Presentation\Requests;

use App\Infrastructure\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QueueProfitAndLossReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'start' => ['required', 'date_format:Y-m-d'],
            'end' => ['required', 'date_format:Y-m-d', 'after_or_equal:start'],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists((new Branch)->getTable(), 'id')
                    ->where('tenant_id', current_tenant_id()),
            ],
        ];
    }
}
