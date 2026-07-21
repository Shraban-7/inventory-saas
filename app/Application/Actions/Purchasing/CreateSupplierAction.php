<?php

namespace App\Application\Actions\Purchasing;

use App\Application\DTOs\SupplierData;
use App\Domain\Exceptions\InvalidPurchasingDataException;
use App\Infrastructure\Models\AuditLog;
use App\Infrastructure\Models\Supplier;
use Illuminate\Support\Facades\DB;

final class CreateSupplierAction
{
    public function handle(SupplierData $data, int $actingUserId): Supplier
    {
        $name = trim($data->name);

        if ($name === '') {
            throw new InvalidPurchasingDataException('Supplier name must not be blank.');
        }

        return DB::transaction(function () use ($data, $actingUserId, $name): Supplier {
            $supplier = Supplier::query()->create([
                'name' => $name,
                'contact_name' => $data->contactName,
                'email' => $data->email,
                'phone' => $data->phone,
                'address' => $data->address,
            ]);

            AuditLog::query()->create([
                'user_id' => $actingUserId,
                'action' => 'SUPPLIER_CREATED',
                'entity_type' => 'supplier',
                'entity_id' => $supplier->getKey(),
                'new_values' => ['name' => $name],
            ]);

            return $supplier;
        });
    }
}
