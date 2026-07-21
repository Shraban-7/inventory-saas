<?php

namespace App\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListSuppliersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['per_page' => ['sometimes', 'integer', 'min:1', 'max:100']];
    }
}
