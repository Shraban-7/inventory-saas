<?php

use App\Application\Actions\Purchasing\ProcessSupplierReturnAction;
use App\Application\DTOs\SupplierReturnData;
use App\Application\DTOs\SupplierReturnItemData;
use App\Application\Listeners\CreateWebhookDeliveries;
use App\Application\Services\WebhookEventPayloadMapper;
use App\Domain\Contracts\DeferredDomainEventPublisher;
use App\Domain\Entities\Quantity;
use App\Domain\Entities\StockBalance;
use App\Domain\Entities\StockMovementType;
use App\Domain\Entities\VariantReorderProfile;
use App\Domain\Entities\WebhookEvent;
use App\Domain\Events\StockLow;
use App\Domain\Repositories\StockRepository;
use App\Domain\Services\StockMovementService;
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
use App\Infrastructure\Models\WebhookDelivery;
use App\Infrastructure\Models\WebhookEndpoint;
use App\Notifications\StockLowNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\PurchasingContext;
use Tests\Support\SalesContext;

afterEach(fn () => app()->forgetInstance('current_tenant'));

function seedLowStockLevel(ProductVariant $variant, int $branchId, string $quantity, int $reorderPoint): void
{
    $tenantId = (int) $variant->tenant_id;
    $variant->forceFill(['reorder_point' => $reorderPoint])->save();
    StockLevel::query()->updateOrCreate(
        [
            'product_variant_id' => $variant->getKey(),
            'branch_id' => $branchId,
        ],
        [
            'tenant_id' => $tenantId,
            'quantity_on_hand' => $quantity,
        ],
    );
    InventoryLot::query()
        ->where('product_variant_id', $variant->getKey())
        ->where('branch_id', $branchId)
        ->delete();
    InventoryLot::query()->create([
        'tenant_id' => $tenantId,
        'product_variant_id' => $variant->getKey(),
        'branch_id' => $branchId,
        'quantity_remaining' => $quantity,
        'unit_cost' => $variant->cost_price,
        'received_at' => '2026-07-01 00:00:00',
    ]);
}

it('maps stock.low webhook payloads with a stable stock movement subject', function () {
    $mapper = app(WebhookEventPayloadMapper::class);
    $event = new StockLow(1, 2, 3, '4.0000', 5, 99);

    expect($mapper->eventName($event))->toBe(WebhookEvent::StockLow)
        ->and($mapper->subjectId($event))->toBe(99)
        ->and($mapper->map($event, 'occurrence-1'))->toMatchArray([
            'id' => 'occurrence-1',
            'event' => 'stock.low',
            'data' => [
                'variant_id' => 2,
                'branch_id' => 3,
                'quantity_on_hand' => '4.0000',
                'reorder_point' => 5,
                'stock_movement_id' => 99,
            ],
        ]);
});

it('dispatches StockLow after commit when an invoice crosses the reorder point', function () {
    $context = SalesContext::create();
    seedLowStockLevel($context->variant, $context->branch->getKey(), '20.0000', 18);
    $this->actingAs($context->user);
    Event::fake([StockLow::class]);

    $this->postJson('/api/v1/invoices', $context->invoicePayload([[
        'variant_id' => $context->variant->getKey(),
        'quantity' => '2.0000',
        'unit_price' => '10.0000',
        'tax_id' => $context->tax->getKey(),
    ]]), ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

    Event::assertDispatched(StockLow::class, fn (StockLow $event): bool => $event->tenantId === (int) $context->tenant->getKey()
        && $event->variantId === (int) $context->variant->getKey()
        && $event->branchId === (int) $context->branch->getKey()
        && $event->quantityOnHand === '18.0000'
        && $event->reorderPoint === 18
        && $event->stockMovementId > 0);
});

it('dispatches StockLow for supplier returns, negative adjustments, and transfer sources', function () {
    $purchasing = PurchasingContext::create();
    $context = $purchasing->sales;
    seedLowStockLevel($context->variant, $context->branch->getKey(), '5.0000', 3);
    Event::fake([StockLow::class]);

    app(ProcessSupplierReturnAction::class)->handle(new SupplierReturnData(
        $context->branch->getKey(),
        $purchasing->supplier->getKey(),
        null,
        'Damaged',
        (string) Str::uuid(),
        hash('sha256', 'supplier-return-low'),
        [new SupplierReturnItemData($context->variant->getKey(), '2.0000')],
    ), $context->user->getKey());

    Event::assertDispatched(StockLow::class, fn (StockLow $event): bool => $event->quantityOnHand === '3.0000');
    Event::fake([StockLow::class]);

    seedLowStockLevel($context->variant, $context->branch->getKey(), '5.0000', 3);
    $this->actingAs($context->user)
        ->postJson('/api/v1/stock-adjustments', [
            'variant_id' => $context->variant->getKey(),
            'branch_id' => $context->branch->getKey(),
            'quantity_delta' => '-2.0000',
            'type' => 'STOCK_ADJUSTMENT_OUT',
            'reason' => 'Shrink',
        ], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated();

    Event::assertDispatched(StockLow::class, fn (StockLow $event): bool => $event->quantityOnHand === '3.0000');
    Event::fake([StockLow::class]);

    seedLowStockLevel($context->variant, $context->branch->getKey(), '5.0000', 3);
    seedLowStockLevel($context->variant, $context->otherBranch->getKey(), '0.0000', 3);
    $this->actingAs($context->user)
        ->postJson('/api/v1/stock-transfers', [
            'from_branch_id' => $context->branch->getKey(),
            'to_branch_id' => $context->otherBranch->getKey(),
            'items' => [['variant_id' => $context->variant->getKey(), 'quantity' => '2.0000']],
        ], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated();

    Event::assertDispatched(StockLow::class, fn (StockLow $event): bool => $event->branchId === (int) $context->branch->getKey()
        && $event->quantityOnHand === '3.0000');
});

it('alerts once on threshold crossing, skips repeats while low, and re-alerts after replenishment', function () {
    $context = SalesContext::create();
    $publisher = new class implements DeferredDomainEventPublisher
    {
        /** @var list<object> */
        public array $events = [];

        public function publishAfterCommit(object $event): void
        {
            $this->events[] = $event;
        }
    };
    $repository = new class($context) implements StockRepository
    {
        public StockBalance $balance;

        private int $nextMovementId = 1;

        public function __construct(private SalesContext $context)
        {
            $this->balance = new StockBalance(1, (int) $context->variant->getKey(), (int) $context->branch->getKey(), Quantity::from('10.0000'));
        }

        public function lockLevels(int $variantId, array $branchIds): array
        {
            return [$this->balance->branchId => $this->balance];
        }

        public function saveBalance(StockBalance $balance): void
        {
            $this->balance = $balance;
        }

        public function appendMovement(int $variantId, int $branchId, Quantity $delta, ?string $unitCost, StockMovementType $type, ?string $sourceType, ?int $sourceId): int
        {
            return $this->nextMovementId++;
        }

        public function reorderProfiles(array $variantIds): array
        {
            return [
                (int) $this->context->variant->getKey() => new VariantReorderProfile(
                    (int) $this->context->variant->getKey(),
                    (int) $this->context->tenant->getKey(),
                    5,
                ),
            ];
        }
    };
    $service = new StockMovementService($repository, null, null, $publisher);

    $service->deduct((int) $context->variant->getKey(), (int) $context->branch->getKey(), '4.0000', null, StockMovementType::StockAdjustmentOut, 'adjustment', 1);
    expect($publisher->events)->toHaveCount(0);

    $service->deduct((int) $context->variant->getKey(), (int) $context->branch->getKey(), '1.0000', null, StockMovementType::StockAdjustmentOut, 'adjustment', 2);
    expect($publisher->events)->toHaveCount(1)
        ->and($publisher->events[0])->toBeInstanceOf(StockLow::class)
        ->and($publisher->events[0]->quantityOnHand)->toBe('5.0000');

    $service->deduct((int) $context->variant->getKey(), (int) $context->branch->getKey(), '1.0000', null, StockMovementType::StockAdjustmentOut, 'adjustment', 3);
    expect($publisher->events)->toHaveCount(1);

    $repository->balance->quantity = Quantity::from('8.0000');
    $service->deduct((int) $context->variant->getKey(), (int) $context->branch->getKey(), '4.0000', null, StockMovementType::StockAdjustmentOut, 'adjustment', 4);
    expect($publisher->events)->toHaveCount(2)
        ->and($publisher->events[1]->quantityOnHand)->toBe('4.0000')
        ->and($publisher->events[1]->stockMovementId)->toBe(4);
});

it('does not dispatch StockLow when the surrounding transaction rolls back', function () {
    $context = SalesContext::create();
    seedLowStockLevel($context->variant, $context->branch->getKey(), '5.0000', 3);
    Event::fake([StockLow::class]);

    try {
        DB::transaction(function () use ($context): void {
            app(StockMovementService::class)->deduct(
                (int) $context->variant->getKey(),
                (int) $context->branch->getKey(),
                '2.0000',
                null,
                StockMovementType::StockAdjustmentOut,
                'adjustment',
                1,
            );

            throw new RuntimeException('force rollback');
        });
    } catch (RuntimeException) {
    }

    Event::assertNotDispatched(StockLow::class);
    expect(StockLevel::query()
        ->where('product_variant_id', $context->variant->getKey())
        ->where('branch_id', $context->branch->getKey())
        ->value('quantity_on_hand'))->toBe('5.0000');
});

it('creates stock.low webhook deliveries only for subscribed endpoints', function () {
    $context = SalesContext::create();
    app()->instance('current_tenant', $context->tenant);
    WebhookEndpoint::query()->create([
        'url' => 'https://example.com/stock',
        'secret' => 'secret',
        'events' => ['stock.low'],
    ]);
    WebhookEndpoint::query()->create([
        'url' => 'https://example.com/invoices',
        'secret' => 'secret',
        'events' => ['invoice.created'],
    ]);
    Queue::fake();
    $movement = StockMovement::query()->create([
        'product_variant_id' => $context->variant->getKey(),
        'branch_id' => $context->branch->getKey(),
        'type' => StockMovementType::StockAdjustmentOut,
        'quantity_delta' => '-1.0000',
    ]);
    $event = new StockLow(
        (int) $context->tenant->getKey(),
        (int) $context->variant->getKey(),
        (int) $context->branch->getKey(),
        '3.0000',
        5,
        (int) $movement->getKey(),
    );

    app(CreateWebhookDeliveries::class)->handle($event);

    expect(WebhookDelivery::query()->count())->toBe(1)
        ->and(WebhookDelivery::query()->firstOrFail()->event)->toBe(WebhookEvent::StockLow)
        ->and(WebhookDelivery::query()->firstOrFail()->payload)->toContain('"event":"stock.low"')
        ->and(WebhookDelivery::query()->firstOrFail()->payload)->toContain('"stock_movement_id":'.$movement->getKey());
});

it('notifies only tenant users authorized for stock.adjust on the affected branch', function () {
    $context = SalesContext::create();
    $managerRole = Role::query()->where('name', 'Manager')->firstOrFail();
    $allowed = User::factory()->create(['tenant_id' => $context->tenant->getKey()]);
    $allowed->roles()->attach($managerRole->getKey(), ['branch_id' => $context->branch->getKey()]);
    $otherBranch = User::factory()->create(['tenant_id' => $context->tenant->getKey()]);
    $otherBranch->roles()->attach($managerRole->getKey(), ['branch_id' => $context->otherBranch->getKey()]);
    $cashier = User::factory()->create(['tenant_id' => $context->tenant->getKey()]);
    $cashier->assignRole(Role::query()->where('name', 'Cashier')->firstOrFail());
    $foreignTenant = Tenant::factory()->create();
    app()->instance('current_tenant', $foreignTenant);
    $foreign = User::factory()->create(['tenant_id' => $foreignTenant->getKey()]);
    $foreign->assignRole(Role::query()->where('name', 'Admin')->firstOrFail());
    app()->instance('current_tenant', $context->tenant);
    Notification::fake();

    event(new StockLow(
        (int) $context->tenant->getKey(),
        (int) $context->variant->getKey(),
        (int) $context->branch->getKey(),
        '2.0000',
        5,
        42,
    ));

    Notification::assertSentTo($context->user, StockLowNotification::class);
    Notification::assertSentTo($allowed, StockLowNotification::class);
    Notification::assertNotSentTo($otherBranch, StockLowNotification::class);
    Notification::assertNotSentTo($cashier, StockLowNotification::class);
    Notification::assertNotSentTo($foreign, StockLowNotification::class);
    Notification::assertSentTo($allowed, function (StockLowNotification $notification) use ($context, $allowed): bool {
        return $notification->toDatabase($allowed) === [
            'variant_id' => (int) $context->variant->getKey(),
            'branch_id' => (int) $context->branch->getKey(),
            'quantity_on_hand' => '2.0000',
            'reorder_point' => 5,
            'stock_movement_id' => 42,
        ];
    });
});

it('filters products by low stock without n+1 and keeps cross-tenant isolation', function () {
    $context = SalesContext::create();
    seedLowStockLevel($context->variant, $context->branch->getKey(), '2.0000', 5);
    seedLowStockLevel($context->secondVariant, $context->branch->getKey(), '20.0000', 5);
    $healthy = Product::query()->create([
        'category_id' => $context->variant->product->category_id,
        'name' => 'Healthy Product',
        'costing_method' => 'fifo',
    ]);
    ProductVariant::query()->create([
        'product_id' => $healthy->getKey(),
        'sku' => 'HEALTHY-1',
        'cost_price' => '1.0000',
        'sale_price' => '2.0000',
        'reorder_point' => 5,
    ]);

    $otherTenant = Tenant::factory()->create();
    app()->instance('current_tenant', $otherTenant);
    $otherCategory = Category::query()->create(['name' => 'Other']);
    $otherProduct = Product::query()->create([
        'category_id' => $otherCategory->getKey(),
        'name' => 'Foreign Low',
        'costing_method' => 'fifo',
    ]);
    $otherVariant = ProductVariant::query()->create([
        'product_id' => $otherProduct->getKey(),
        'sku' => 'FOREIGN-LOW',
        'cost_price' => '1.0000',
        'sale_price' => '2.0000',
        'reorder_point' => 10,
    ]);
    $otherBranch = Branch::factory()->create(['tenant_id' => $otherTenant->getKey()]);
    StockLevel::query()->create([
        'product_variant_id' => $otherVariant->getKey(),
        'branch_id' => $otherBranch->getKey(),
        'quantity_on_hand' => '1.0000',
    ]);
    app()->instance('current_tenant', $context->tenant);

    $this->actingAs($context->user);
    DB::flushQueryLog();
    DB::enableQueryLog();
    $response = $this->getJson('/api/v1/products?filter[low_stock]=true&per_page=50')->assertSuccessful();
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    $response->assertJsonFragment(['name' => 'Widget'])
        ->assertJsonMissing(['name' => 'Healthy Product'])
        ->assertJsonMissing(['name' => 'Foreign Low'])
        ->assertJsonFragment(['sku' => $context->variant->sku])
        ->assertJsonMissing(['sku' => $context->secondVariant->sku]);

    expect($queryCount)->toBeLessThanOrEqual(8);

    $this->getJson('/api/v1/products')->assertSuccessful()->assertJsonFragment(['name' => 'Healthy Product']);
    $this->getJson('/api/v1/products?filter[low_stock]=maybe')->assertUnprocessable();
    $this->getJson('/api/v1/products?filter[low_stock]=true&filter[branch_id]='.$context->otherBranch->getKey())
        ->assertSuccessful()
        ->assertJsonMissing(['sku' => $context->variant->sku]);
});

it('rejects unauthorized branch filters for branch-scoped stock managers', function () {
    $context = SalesContext::create();
    $manager = Role::query()->where('name', 'Manager')->firstOrFail();
    $context->user->roles()->detach();
    $context->user->roles()->attach($manager->getKey(), ['branch_id' => $context->branch->getKey()]);
    seedLowStockLevel($context->variant, $context->branch->getKey(), '1.0000', 5);
    seedLowStockLevel($context->variant, $context->otherBranch->getKey(), '1.0000', 5);
    $this->actingAs($context->user);

    $this->getJson('/api/v1/products?filter[low_stock]=true')
        ->assertSuccessful()
        ->assertJsonFragment(['sku' => $context->variant->sku]);
    $this->getJson('/api/v1/products?filter[low_stock]=true&filter[branch_id]='.$context->otherBranch->getKey())
        ->assertForbidden();
});

it('denies low-stock filters to product viewers without stock.adjust and stays tenant isolated', function () {
    $context = SalesContext::create('Cashier');
    seedLowStockLevel($context->variant, $context->branch->getKey(), '1.0000', 5);

    $foreignTenant = Tenant::factory()->create();
    app()->instance('current_tenant', $foreignTenant);
    $foreignCategory = Category::query()->create(['name' => 'Foreign']);
    $foreignProduct = Product::query()->create([
        'category_id' => $foreignCategory->getKey(),
        'name' => 'Foreign Low Stock',
        'costing_method' => 'fifo',
    ]);
    $foreignVariant = ProductVariant::query()->create([
        'product_id' => $foreignProduct->getKey(),
        'sku' => 'FOREIGN-VIEW-LOW',
        'cost_price' => '1.0000',
        'sale_price' => '2.0000',
        'reorder_point' => 10,
    ]);
    $foreignBranch = Branch::factory()->create(['tenant_id' => $foreignTenant->getKey()]);
    StockLevel::query()->create([
        'tenant_id' => $foreignTenant->getKey(),
        'product_variant_id' => $foreignVariant->getKey(),
        'branch_id' => $foreignBranch->getKey(),
        'quantity_on_hand' => '1.0000',
    ]);
    app()->instance('current_tenant', $context->tenant);

    $this->actingAs($context->user)
        ->getJson('/api/v1/products')
        ->assertSuccessful()
        ->assertJsonFragment(['name' => 'Widget'])
        ->assertJsonMissing(['name' => 'Foreign Low Stock']);

    $this->getJson('/api/v1/products?filter[low_stock]=true')->assertForbidden();
    $this->getJson('/api/v1/products?filter[branch_id]='.$context->branch->getKey())->assertForbidden();
    $this->getJson('/api/v1/products?filter[low_stock]=true&filter[branch_id]='.$context->branch->getKey())
        ->assertForbidden();
    $this->getJson('/api/v1/products?filter[low_stock]=true')
        ->assertForbidden()
        ->assertJsonMissing(['sku' => 'FOREIGN-VIEW-LOW']);
});
