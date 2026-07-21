<?php

namespace App\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'default_branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('tenant_id', current_tenant_id())],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:254'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'array'],
            'address.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array{name: string, default_branch_id: int|null, email: string|null, phone: string|null, address: array<string, mixed>|null} */
    public function customerData(): array
    {
        $data = $this->validated();
        $address = $data['address'] ?? null;

        return [
            'name' => (string) $data['name'],
            'default_branch_id' => isset($data['default_branch_id']) ? (int) $data['default_branch_id'] : null,
            'email' => isset($data['email']) ? (string) $data['email'] : null,
            'phone' => isset($data['phone']) ? (string) $data['phone'] : null,
            'address' => is_array($address) ? $address : null,
        ];
    }
}
