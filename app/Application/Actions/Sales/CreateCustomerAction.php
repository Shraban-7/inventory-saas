<?php

namespace App\Application\Actions\Sales;

use App\Infrastructure\Models\Customer;

final class CreateCustomerAction
{
    /** @param array{name: string, default_branch_id: int|null, email: string|null, phone: string|null, address: array<string, mixed>|null} $data */
    public function handle(array $data): Customer
    {
        return Customer::query()->create($data);
    }
}
