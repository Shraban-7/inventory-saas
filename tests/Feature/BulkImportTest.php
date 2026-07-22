<?php

use App\Application\Actions\Inventory\AdjustStockAction;
use App\Application\Actions\Inventory\CreateProductAction;
use App\Application\Jobs\ProcessBulkImportChunkJob;
use App\Application\Jobs\ProcessBulkImportJob;
use App\Application\Services\BranchAuthorizationService;
use App\Application\Services\CanonicalJson;
use App\Domain\Entities\BulkImportRowStatus;
use App\Domain\Entities\BulkImportStatus;
use App\Domain\Entities\BulkImportType;
use App\Infrastructure\Models\Branch;
use App\Infrastructure\Models\BulkImport;
use App\Infrastructure\Models\BulkImportRow;
use App\Infrastructure\Models\Category;
use App\Infrastructure\Models\Product;
use App\Infrastructure\Models\ProductVariant;
use App\Infrastructure\Models\Role;
use App\Infrastructure\Models\StockAdjustment;
use App\Infrastructure\Models\StockLevel;
use App\Infrastructure\Models\StockMovement;
use App\Infrastructure\Models\Tenant;
use App\Infrastructure\Models\User;
use App\Notifications\BulkImportFinishedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** @return array{tenant: Tenant, user: User, category: Category} */
function bulkImportProductContext(): array
{
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);
    $user = User::factory()->create(['tenant_id' => $tenant->getKey()]);
    $user->assignRole(Role::query()->where('name', 'Admin')->firstOrFail());
    $category = Category::query()->create(['name' => 'Imported']);

    return compact('tenant', 'user', 'category');
}

function createBulkImport(
    Tenant $tenant,
    User $user,
    BulkImportType $type,
    string $csv,
): BulkImport {
    app()->instance('current_tenant', $tenant);
    $path = 'bulk-imports/'.$tenant->getKey().'/'.Str::uuid().'.csv';
    Storage::disk('local')->put($path, $csv);

    return BulkImport::query()->create([
        'tenant_id' => $tenant->getKey(),
        'requested_by_user_id' => $user->getKey(),
        'type' => $type,
        'status' => BulkImportStatus::Queued,
        'disk' => 'local',
        'path' => $path,
    ]);
}

function runBulkImport(BulkImport $import): void
{
    config()->set('queue.default', 'sync');
    (new ProcessBulkImportJob(
        (int) $import->tenant_id,
        (string) $import->getKey(),
    ))->handle();
}

function productCsv(Category $category, string $sku, string $name = 'Imported Widget'): string
{
    $headers = implode(',', config('imports.headers.products'));
    $row = implode(',', [
        $category->getKey(),
        $name,
        'Description',
        'fifo',
        $sku,
        '',
        '10.0000',
        '15.0000',
        '2',
    ]);

    return "{$headers}\n{$row}\n";
}

beforeEach(function (): void {
    Storage::fake('local');
});

afterEach(fn () => app()->forgetInstance('current_tenant'));

it('accepts a product CSV upload and returns a tracker with job_id', function () {
    $context = bulkImportProductContext();
    Queue::fake();

    $response = $this->actingAs($context['user'])->post('/api/v1/bulk/products', [
        'file' => UploadedFile::fake()->createWithContent(
            'products.csv',
            implode(',', config('imports.headers.products'))."\n",
        ),
    ]);

    $response->assertAccepted()
        ->assertJsonPath('data.type', BulkImportType::Products->value)
        ->assertJsonPath('data.status', BulkImportStatus::Queued->value)
        ->assertJsonPath('data.job_id', $response->json('data.id'));

    $import = BulkImport::query()->findOrFail($response->json('data.job_id'));
    expect(Storage::disk('local')->exists($import->path))->toBeTrue();
    Queue::assertPushed(ProcessBulkImportJob::class);
});

it('rate limits bulk uploads per tenant and user', function () {
    Queue::fake();
    $context = bulkImportProductContext();
    $csv = implode(',', config('imports.headers.products'))."\n";
    $upload = fn () => UploadedFile::fake()->createWithContent('products.csv', $csv);

    for ($attempt = 0; $attempt < 10; $attempt++) {
        $this->actingAs($context['user'])
            ->post('/api/v1/bulk/products', ['file' => $upload()])
            ->assertAccepted();
    }

    $this->actingAs($context['user'])
        ->post('/api/v1/bulk/products', ['file' => $upload()])
        ->assertTooManyRequests();

    $peer = User::factory()->create(['tenant_id' => $context['tenant']->getKey()]);
    $peer->assignRole(Role::query()->where('name', 'Admin')->firstOrFail());

    $this->actingAs($peer)
        ->post('/api/v1/bulk/products', ['file' => $upload()])
        ->assertAccepted();

    $otherTenant = bulkImportProductContext();
    $this->actingAs($otherTenant['user'])
        ->post('/api/v1/bulk/products', ['file' => $upload()])
        ->assertAccepted();
});

it('imports the same product CSV twice without duplicate variants', function () {
    Notification::fake();
    $context = bulkImportProductContext();
    $csv = productCsv($context['category'], 'IMPORT-SKU-1');

    $first = createBulkImport($context['tenant'], $context['user'], BulkImportType::Products, $csv);
    runBulkImport($first);
    $second = createBulkImport($context['tenant'], $context['user'], BulkImportType::Products, $csv);
    runBulkImport($second);

    expect(Product::query()->count())->toBe(1)
        ->and(ProductVariant::query()->where('sku', 'IMPORT-SKU-1')->count())->toBe(1)
        ->and($first->refresh()->succeeded_rows)->toBe(1)
        ->and($second->refresh()->skipped_rows)->toBe(1);
});

it('isolates malformed product rows and reports their errors', function () {
    Notification::fake();
    $context = bulkImportProductContext();
    $headers = implode(',', config('imports.headers.products'));
    $valid = implode(',', [
        $context['category']->getKey(), 'Valid Widget', '', 'fifo', 'VALID-SKU', '', '1.0000', '2.0000', '0',
    ]);
    $invalid = implode(',', [
        $context['category']->getKey(), '', '', 'fifo', 'INVALID-SKU', '', '1.0000', '2.0000', '0',
    ]);
    $import = createBulkImport(
        $context['tenant'],
        $context['user'],
        BulkImportType::Products,
        "{$headers}\n{$valid}\n{$invalid}\n",
    );

    runBulkImport($import);

    $import->refresh();
    expect($import->status)->toBe(BulkImportStatus::Completed)
        ->and($import->succeeded_rows)->toBe(1)
        ->and($import->failed_rows)->toBe(1)
        ->and(ProductVariant::query()->count())->toBe(1);

    $this->actingAs($context['user'])
        ->getJson("/api/v1/bulk/imports/{$import->getKey()}/errors")
        ->assertSuccessful()
        ->assertJsonPath('data.0.row', 3)
        ->assertJsonPath('data.0.code', 'validation_failed');

    Notification::assertSentTo($context['user'], BulkImportFinishedNotification::class);
});

it('replays a stock CSV without applying the adjustment twice', function () {
    Notification::fake();
    $context = bulkImportProductContext();
    $branch = Branch::factory()->create(['tenant_id' => $context['tenant']->getKey()]);
    $product = Product::query()->create([
        'category_id' => $context['category']->getKey(),
        'name' => 'Stock Widget',
        'costing_method' => 'fifo',
    ]);
    $variant = ProductVariant::query()->create([
        'product_id' => $product->getKey(),
        'sku' => 'STOCK-IMPORT-SKU',
        'cost_price' => '4.0000',
        'sale_price' => '8.0000',
    ]);
    $key = (string) Str::uuid();
    $headers = implode(',', config('imports.headers.stock_adjustments'));
    $row = implode(',', [
        $variant->getKey(),
        $branch->getKey(),
        '5.0000',
        'STOCK_ADJUSTMENT_IN',
        'Opening stock',
        $key,
    ]);
    $csv = "{$headers}\n{$row}\n";

    runBulkImport(createBulkImport(
        $context['tenant'],
        $context['user'],
        BulkImportType::StockAdjustments,
        $csv,
    ));
    runBulkImport(createBulkImport(
        $context['tenant'],
        $context['user'],
        BulkImportType::StockAdjustments,
        $csv,
    ));

    expect(StockAdjustment::query()->count())->toBe(1)
        ->and(StockMovement::query()->count())->toBe(1)
        ->and(StockLevel::query()->firstOrFail()->quantity_on_hand)->toBe('5.0000');
});

it('keeps a single batch and existing rows when the parent job runs twice', function () {
    Notification::fake();
    $context = bulkImportProductContext();
    $import = createBulkImport(
        $context['tenant'],
        $context['user'],
        BulkImportType::Products,
        productCsv($context['category'], 'PARENT-RACE-SKU'),
    );

    runBulkImport($import);
    $import->refresh();

    $batchId = $import->batch_id;
    $rowIds = $import->rows()->orderBy('id')->pluck('id')->all();

    expect($batchId)->not->toBeNull()
        ->and($rowIds)->not->toBeEmpty();

    runBulkImport($import);
    $import->refresh();

    expect($import->batch_id)->toBe($batchId)
        ->and($import->rows()->orderBy('id')->pluck('id')->all())->toBe($rowIds)
        ->and(ProductVariant::query()->where('sku', 'PARENT-RACE-SKU')->count())->toBe(1);
});

it('marks a product row succeeded on bookkeeping retry when target_key already claims the sku', function () {
    Notification::fake();
    $context = bulkImportProductContext();
    $import = createBulkImport(
        $context['tenant'],
        $context['user'],
        BulkImportType::Products,
        productCsv($context['category'], 'RETRY-SKU'),
    );
    $row = BulkImportRow::query()->create([
        'tenant_id' => $context['tenant']->getKey(),
        'bulk_import_id' => $import->getKey(),
        'row_number' => 2,
        'target_key' => 'RETRY-SKU',
        'status' => BulkImportRowStatus::Pending,
    ]);
    Product::query()->create([
        'category_id' => $context['category']->getKey(),
        'name' => 'Already created',
        'costing_method' => 'fifo',
    ])->variants()->create([
        'sku' => 'RETRY-SKU',
        'cost_price' => '10.0000',
        'sale_price' => '15.0000',
        'reorder_point' => 2,
    ]);

    $job = new ProcessBulkImportChunkJob(
        (int) $context['tenant']->getKey(),
        (string) $import->getKey(),
        (int) $context['user']->getKey(),
        BulkImportType::Products->value,
        config('imports.headers.products'),
        [[
            'row_id' => (int) $row->getKey(),
            'row_number' => 2,
            'values' => [
                (string) $context['category']->getKey(),
                'Already created',
                'Description',
                'fifo',
                'RETRY-SKU',
                '',
                '10.0000',
                '15.0000',
                '2',
            ],
        ]],
    );
    $job->beforeCommit();
    $job->handle(
        app(CreateProductAction::class),
        app(AdjustStockAction::class),
        app(BranchAuthorizationService::class),
        app(CanonicalJson::class),
    );

    expect($row->refresh()->status)->toBe(BulkImportRowStatus::Succeeded)
        ->and(ProductVariant::query()->where('sku', 'RETRY-SKU')->count())->toBe(1);
});
