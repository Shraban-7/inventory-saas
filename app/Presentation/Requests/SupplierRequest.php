<?php

namespace App\Presentation\Requests;

use App\Application\DTOs\SupplierData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use LogicException;

class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:254'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'array', 'max:20'],
            'address.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $address = $this->input('address');

            if (! is_array($address)) {
                return;
            }

            foreach (array_keys($address) as $key) {
                if (! is_string($key) || strlen($key) > 50) {
                    $validator->errors()->add('address', 'Address keys must be strings no longer than 50 characters.');
                    break;
                }
            }
        }];
    }

    public function supplierData(): SupplierData
    {
        $data = $this->validated();
        $address = $data['address'] ?? null;

        if ($address !== null && ! is_array($address)) {
            throw new LogicException('Validated supplier address must be an array or null.');
        }

        return new SupplierData(
            (string) $data['name'],
            isset($data['contact_name']) ? (string) $data['contact_name'] : null,
            isset($data['email']) ? (string) $data['email'] : null,
            isset($data['phone']) ? (string) $data['phone'] : null,
            $address,
        );
    }
}
