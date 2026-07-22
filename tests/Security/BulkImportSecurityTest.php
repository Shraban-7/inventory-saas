<?php

use App\Application\Jobs\ProcessBulkImportJob;
use App\Domain\Entities\BulkImportStatus;
use App\Domain\Entities\BulkImportType;
use App\Infrastructure\Models\Branch;
use App\Infrastructure\Models\BulkImport;
use App\Infrastructure\Models\Category;
use App\Infrastructure\Models\Product;
use App\Infrastructure\Models\ProductVariant;
use App\Infrastructure\Models\Role;
use App\Infrastructure\Models\Tenant;
use App\Infrastructure\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
});

afterEach(fn () => app()->forgetInstance('current_tenant'));

it('enforces stock permission and branch authorization for bulk imports', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);
    $allowedBranch = Branch::factory()->create(['tenant_id' => $tenant->getKey()]);
    $deniedBranch = Branch::factory()->create(['tenant_id' => $tenant->getKey()]);
    $category = Category::query()->create(['name' => 'Security']);
    $product = Product::query()->create([
        'category_id' => $category->getKey(),
        'name' => 'Secured stock',
        'costing_method' => 'fifo',
    ]);
    $variant = ProductVariant::query()->create([
        'product_id' => $product->getKey(),
        'sku' => 'SECURE-STOCK',
        'cost_price' => '1.0000',
        'sale_price' => '2.0000',
    ]);
    $manager = User::factory()->create(['tenant_id' => $tenant->getKey()]);
    $managerRole = Role::query()->where('name', 'Manager')->firstOrFail();
    $manager->roles()->attach($managerRole->getKey(), ['branch_id' => $allowedBranch->getKey()]);
    $cashier = User::factory()->create(['tenant_id' => $tenant->getKey()]);
    $cashier->assignRole(Role::query()->where('name', 'Cashier')->firstOrFail());

    $this->actingAs($cashier)->post('/api/v1/bulk/stock-adjustments', [
        'file' => UploadedFile::fake()->createWithContent(
            'stock.csv',
            implode(',', config('imports.headers.stock_adjustments'))."\n",
        ),
    ])->assertForbidden();

    config()->set('queue.default', 'sync');
    $path = 'bulk-imports/'.$tenant->getKey().'/'.Str::uuid().'.csv';
    Storage::disk('local')->put(
        $path,
        implode(',', config('imports.headers.stock_adjustments'))."\n".implode(',', [
            $variant->getKey(),
            $deniedBranch->getKey(),
            '1.0000',
            'STOCK_ADJUSTMENT_IN',
            'Unauthorized branch',
            Str::uuid(),
        ])."\n",
    );
    $import = BulkImport::query()->create([
        'tenant_id' => $tenant->getKey(),
        'requested_by_user_id' => $manager->getKey(),
        'type' => BulkImportType::StockAdjustments,
        'status' => BulkImportStatus::Queued,
        'disk' => 'local',
        'path' => $path,
    ]);

    (new ProcessBulkImportJob((int) $tenant->getKey(), (string) $import->getKey()))->handle();

    expect($import->refresh()->failed_rows)->toBe(1)
        ->and($import->rows()->firstOrFail()->error_code)->toBe('validation_failed');
});

it('returns not found for another tenants import tracker', function () {
    $ownerTenant = Tenant::factory()->create();
    app()->instance('current_tenant', $ownerTenant);
    $owner = User::factory()->create(['tenant_id' => $ownerTenant->getKey()]);
    $owner->assignRole(Role::query()->where('name', 'Admin')->firstOrFail());
    $import = BulkImport::query()->create([
        'tenant_id' => $ownerTenant->getKey(),
        'requested_by_user_id' => $owner->getKey(),
        'type' => BulkImportType::Products,
        'status' => BulkImportStatus::Queued,
        'disk' => 'local',
        'path' => 'bulk-imports/missing.csv',
    ]);

    $otherTenant = Tenant::factory()->create();
    app()->instance('current_tenant', $otherTenant);
    $otherAdmin = User::factory()->create(['tenant_id' => $otherTenant->getKey()]);
    $otherAdmin->assignRole(Role::query()->where('name', 'Admin')->firstOrFail());

    $this->actingAs($otherAdmin)
        ->getJson("/api/v1/bulk/imports/{$import->getKey()}")
        ->assertNotFound();
});
