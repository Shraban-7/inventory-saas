<?php

namespace App\Presentation\Requests;

use App\Domain\Entities\StockMovementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'variant_id' => ['required', 'integer', Rule::exists('product_variants', 'id')->where('tenant_id', current_tenant_id())],
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('tenant_id', current_tenant_id())],
            'quantity_delta' => ['required', 'numeric', 'decimal:0,4', 'not_in:0,0.0,0.0000'],
            'reason' => ['required', 'string', 'max:255'],
            'type' => ['required', new Enum(StockMovementType::class), Rule::in([
                StockMovementType::StockAdjustmentIn->value,
                StockMovementType::StockAdjustmentOut->value,
            ])],
        ];
    }
}
