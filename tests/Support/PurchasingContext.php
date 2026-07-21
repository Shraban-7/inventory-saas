<?php

namespace Tests\Support;

use App\Infrastructure\Models\Supplier;

final readonly class PurchasingContext
{
    public function __construct(
        public SalesContext $sales,
        public Supplier $supplier,
        public Supplier $secondSupplier,
    ) {}

    public static function create(string $role = 'Admin'): self
    {
        $sales = SalesContext::create($role);
        $supplier = Supplier::factory()->create([
            'tenant_id' => $sales->tenant->getKey(),
            'name' => 'Northwind Supply',
            'contact_name' => 'Ada Buyer',
            'email' => 'ada@example.test',
            'phone' => '+1-555-0100',
            'address' => ['city' => 'Dhaka', 'country' => 'BD'],
        ]);
        $secondSupplier = Supplier::factory()->create([
            'tenant_id' => $sales->tenant->getKey(),
            'name' => 'Contoso Wholesale',
        ]);

        return new self($sales, $supplier, $secondSupplier);
    }

    /** @return array<string, mixed> */
    public function purchaseOrderPayload(?array $items = null): array
    {
        return [
            'branch_id' => $this->sales->branch->getKey(),
            'supplier_id' => $this->supplier->getKey(),
            'order_date' => '2026-07-22',
            'expected_date' => '2026-07-29',
            'notes' => 'Restock',
            'items' => $items ?? [[
                'variant_id' => $this->sales->variant->getKey(),
                'quantity' => '5.0000',
                'unit_cost' => '4.2500',
            ]],
        ];
    }

    /** @return array<string, mixed> */
    public function goodsReceiptPayload(?int $purchaseOrderId = null, string $quantity = '5.0000', string $cost = '4.2500'): array
    {
        return [
            'branch_id' => $this->sales->branch->getKey(),
            'supplier_id' => $this->supplier->getKey(),
            'purchase_order_id' => $purchaseOrderId,
            'received_at' => '2026-07-22T10:00:00+00:00',
            'notes' => 'Dock receipt',
            'items' => [[
                'variant_id' => $this->sales->variant->getKey(),
                'quantity' => $quantity,
                'unit_cost' => $cost,
            ]],
        ];
    }

    /** @return array<string, mixed> */
    public function billPayload(?int $grnId = null, string $number = 'SUP-INV-1001', string $quantity = '5.0000', string $cost = '4.2500'): array
    {
        return [
            'branch_id' => $this->sales->branch->getKey(),
            'supplier_id' => $this->supplier->getKey(),
            'grn_id' => $grnId,
            'bill_number' => $number,
            'bill_date' => '2026-07-22',
            'due_date' => '2026-08-22',
            'items' => [[
                'variant_id' => $this->sales->variant->getKey(),
                'tax_id' => $this->sales->tax->getKey(),
                'quantity' => $quantity,
                'unit_cost' => $cost,
            ]],
        ];
    }
}
