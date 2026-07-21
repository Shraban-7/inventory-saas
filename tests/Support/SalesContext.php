<?php

namespace Tests\Support;

use App\Infrastructure\Models\Branch;
use App\Infrastructure\Models\Category;
use App\Infrastructure\Models\ChartOfAccount;
use App\Infrastructure\Models\Customer;
use App\Infrastructure\Models\InventoryLot;
use App\Infrastructure\Models\Product;
use App\Infrastructure\Models\ProductVariant;
use App\Infrastructure\Models\Role;
use App\Infrastructure\Models\StockLevel;
use App\Infrastructure\Models\Tax;
use App\Infrastructure\Models\Tenant;
use App\Infrastructure\Models\User;

final readonly class SalesContext
{
    public function __construct(
        public Tenant $tenant,
        public User $user,
        public Branch $branch,
        public Branch $otherBranch,
        public Customer $customer,
        public ProductVariant $variant,
        public ProductVariant $secondVariant,
        public Tax $tax,
    ) {}

    public static function create(string $role = 'Admin'): self
    {
        $tenant = Tenant::factory()->create();
        app()->instance('current_tenant', $tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->getKey()]);
        $otherBranch = Branch::factory()->create(['tenant_id' => $tenant->getKey()]);
        $customer = Customer::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'default_branch_id' => $branch->getKey(),
        ]);
        $category = Category::query()->create(['name' => 'Sales']);
        $product = Product::query()->create([
            'category_id' => $category->getKey(),
            'name' => 'Widget',
            'costing_method' => 'fifo',
        ]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'sku' => 'SALE-'.fake()->unique()->numerify('#####'),
            'cost_price' => '4.2500',
            'sale_price' => '10.0000',
        ]);
        $secondVariant = ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'sku' => 'SALE-'.fake()->unique()->numerify('#####'),
            'cost_price' => '2.5000',
            'sale_price' => '6.0000',
        ]);

        foreach ([$variant, $secondVariant] as $stockVariant) {
            StockLevel::query()->create([
                'product_variant_id' => $stockVariant->getKey(),
                'branch_id' => $branch->getKey(),
                'quantity_on_hand' => '20.0000',
            ]);
            InventoryLot::query()->create([
                'product_variant_id' => $stockVariant->getKey(),
                'branch_id' => $branch->getKey(),
                'quantity_remaining' => '20.0000',
                'unit_cost' => $stockVariant->cost_price,
                'received_at' => '2026-07-01 00:00:00',
            ]);
        }

        $tax = Tax::query()->create([
            'coa_id' => ChartOfAccount::query()->where('code', '2100')->valueOrFail('id'),
            'name' => 'VAT',
            'rate' => '7.5000',
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $user->assignRole(Role::query()->where('name', $role)->firstOrFail());

        return new self($tenant, $user, $branch, $otherBranch, $customer, $variant, $secondVariant, $tax);
    }

    /** @return array<string, mixed> */
    public function invoicePayload(?array $items = null): array
    {
        return [
            'branch_id' => $this->branch->getKey(),
            'customer_id' => $this->customer->getKey(),
            'invoice_date' => '2026-07-22',
            'due_date' => '2026-08-22',
            'notes' => 'Test invoice',
            'items' => $items ?? [[
                'variant_id' => $this->variant->getKey(),
                'quantity' => '2.0000',
                'unit_price' => '10.0000',
                'tax_id' => $this->tax->getKey(),
            ]],
        ];
    }
}
