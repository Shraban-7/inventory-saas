<?php

namespace App\Presentation\Resources;

use App\Infrastructure\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $customer = $this->resource;

        if (! $customer instanceof Customer) {
            return [];
        }

        return [
            'id' => $customer->getKey(),
            'default_branch_id' => $customer->default_branch_id,
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'address' => $customer->address,
            'created_at' => $customer->created_at,
            'updated_at' => $customer->updated_at,
        ];
    }
}
