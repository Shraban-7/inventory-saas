<?php

namespace App\Application\Actions\Sales;

use App\Infrastructure\Models\Customer;

final class UpdateCustomerAction
{
    /** @param array{name: string, default_branch_id: int|null, email: string|null, phone: string|null, address: array<string, mixed>|null} $data */
    public function handle(Customer $customer, array $data): Customer
    {
        $customer->update($data);

        return $customer->refresh();
    }
}
