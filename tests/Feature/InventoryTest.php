<?php

use App\Infrastructure\Models\Attribute as ProductAttribute;
use App\Infrastructure\Models\AttributeValue;
use App\Infrastructure\Models\AuditLog;
use App\Infrastructure\Models\Branch;
use App\Infrastructure\Models\Category;
use App\Infrastructure\Models\InventoryLot;
use App\Infrastructure\Models\Product;
use App\Infrastructure\Models\ProductVariant;
use App\Infrastructure\Models\Role;
use App\Infrastructure\Models\StockLevel;
use App\Infrastructure\Models\StockMovement;
use App\Infrastructure\Models\Tenant;
use App\Infrastructure\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** @return array{tenant: Tenant, user: User, category: Category, product: Product, variant: ProductVariant, from: Branch, to: Branch} */
function inventorySetup(): array
{
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);
    $category = Category::query()->create(['name' => 'General']);
    $product = Product::query()->create(['category_id' => $category->getKey(), 'name' => 'Widget', 'costing_method' => 'fifo']);
    $variant = ProductVariant::query()->create([
        'product_id' => $product->getKey(),
        'sku' => 'WIDGET-1',
        'cost_price' => '10.0000',
        'sale_price' => '15.0000',
    ]);
    $from = Branch::factory()->create(['tenant_id' => $tenant->getKey()]);
    $to = Branch::factory()->create(['tenant_id' => $tenant->getKey()]);
    $user = User::factory()->create(['tenant_id' => $tenant->getKey()]);
    $user->assignRole(Role::query()->where('name', 'Admin')->firstOrFail());
    test()->actingAs($user);

    return compact('tenant', 'user', 'category', 'product', 'variant', 'from', 'to');
}

afterEach(fn () => app()->forgetInstance('current_tenant'));

it('enforces tenant scoped variant sku uniqueness', function () {
    $first = inventorySetup();

    expect(fn () => ProductVariant::query()->create([
        'product_id' => $first['product']->getKey(),
        'sku' => 'WIDGET-1',
        'cost_price' => '1',
        'sale_price' => '2',
    ]))->toThrow(QueryException::class);

    $otherTenant = Tenant::factory()->create();
    app()->instance('current_tenant', $otherTenant);
    $category = Category::query()->create(['name' => 'Other']);
    $product = Product::query()->create(['category_id' => $category->getKey(), 'name' => 'Other', 'costing_method' => 'fifo']);

    expect(ProductVariant::query()->create([
        'product_id' => $product->getKey(),
        'sku' => 'WIDGET-1',
        'cost_price' => '1',
        'sale_price' => '2',
    ]))->toBeInstanceOf(ProductVariant::class);
});

it('creates an atomic idempotent stock adjustment', function () {
    $context = inventorySetup();
    $key = (string) Str::uuid();
    $payload = [
        'variant_id' => $context['variant']->getKey(),
        'branch_id' => $context['from']->getKey(),
        'quantity_delta' => '5.0000',
        'type' => 'STOCK_ADJUSTMENT_IN',
        'reason' => 'Opening count',
    ];

    $this->postJson('/api/v1/stock-adjustments', $payload, ['Idempotency-Key' => $key])->assertCreated();
    $this->postJson('/api/v1/stock-adjustments', $payload, ['Idempotency-Key' => $key])->assertCreated();
    app()->instance('current_tenant', $context['tenant']);

    expect(StockMovement::query()->count())->toBe(1)
        ->and(StockLevel::query()->firstOrFail()->quantity_on_hand)->toBe('5.0000')
        ->and(AuditLog::query()->where('action', 'STOCK_ADJUSTED')->count())->toBe(1);
});

it('transfers stock atomically between branches', function () {
    $context = inventorySetup();
    StockLevel::query()->create([
        'product_variant_id' => $context['variant']->getKey(),
        'branch_id' => $context['from']->getKey(),
        'quantity_on_hand' => '5.0000',
    ]);
    InventoryLot::query()->create([
        'product_variant_id' => $context['variant']->getKey(),
        'branch_id' => $context['from']->getKey(),
        'quantity_remaining' => '5.0000',
        'unit_cost' => '10.0000',
        'received_at' => '2026-07-21 00:00:00',
    ]);

    $this->postJson('/api/v1/stock-transfers', [
        'from_branch_id' => $context['from']->getKey(),
        'to_branch_id' => $context['to']->getKey(),
        'items' => [['variant_id' => $context['variant']->getKey(), 'quantity' => '2.0000']],
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

    app()->instance('current_tenant', $context['tenant']);
    expect(StockLevel::query()->where('branch_id', $context['from']->getKey())->value('quantity_on_hand'))->toBe('3.0000')
        ->and(StockLevel::query()->where('branch_id', $context['to']->getKey())->value('quantity_on_hand'))->toBe('2.0000')
        ->and(StockMovement::query()->count())->toBe(2);
});

it('requires at least one variant when creating a product', function () {
    $context = inventorySetup();

    $this->postJson('/api/v1/products', [
        'category_id' => $context['category']->getKey(),
        'name' => 'No Variant',
        'variants' => [],
    ])->assertUnprocessable();
});

it('lists only current tenant products', function () {
    $context = inventorySetup();
    $otherTenant = Tenant::factory()->create();
    app()->instance('current_tenant', $otherTenant);
    $category = Category::query()->create(['name' => 'Other']);
    Product::query()->create(['category_id' => $category->getKey(), 'name' => 'Hidden Product', 'costing_method' => 'fifo']);

    $this->actingAs($context['user'])->getJson('/api/v1/products')
        ->assertSuccessful()
        ->assertJsonFragment(['name' => 'Widget'])
        ->assertJsonMissing(['name' => 'Hidden Product']);
});

it('eager loads variants and attribute values with a bounded query count', function () {
    $context = inventorySetup();
    $attribute = ProductAttribute::query()->create(['name' => 'Color']);
    $value = AttributeValue::query()->create(['attribute_id' => $attribute->getKey(), 'value' => 'Blue']);
    $context['variant']->attributeValues()->attach($value->getKey(), ['tenant_id' => $context['tenant']->getKey()]);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $products = Product::query()->with('variants.attributeValues')->get();
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queryCount)->toBeLessThanOrEqual(3)
        ->and($products->firstOrFail()->relationLoaded('variants'))->toBeTrue()
        ->and($products->firstOrFail()->variants->firstOrFail()->relationLoaded('attributeValues'))->toBeTrue();
});
