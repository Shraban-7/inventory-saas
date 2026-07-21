<?php

namespace App\Presentation\Resources;

use App\Infrastructure\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $supplier = $this->resource;

        if (! $supplier instanceof Supplier) {
            return [];
        }

        return [
            'id' => $supplier->getKey(),
            'name' => $supplier->name,
            'contact_name' => $supplier->contact_name,
            'email' => $supplier->email,
            'phone' => $supplier->phone,
            'address' => $supplier->address,
            'created_at' => $supplier->created_at,
            'updated_at' => $supplier->updated_at,
        ];
    }
}
