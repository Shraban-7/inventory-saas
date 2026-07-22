<?php

use App\Application\Jobs\DispatchArchiveExportsJob;
use App\Application\Jobs\ExportArchiveDatasetJob;
use App\Application\Services\Archive\ArchiveExportService;
use App\Domain\Entities\ArchiveDataset;
use App\Domain\Entities\ArchiveExportStatus;
use App\Domain\Entities\StockMovementType;
use App\Infrastructure\Models\ArchiveExport;
use App\Infrastructure\Models\AuditLog;
use App\Infrastructure\Models\Branch;
use App\Infrastructure\Models\Category;
use App\Infrastructure\Models\ChartOfAccount;
use App\Infrastructure\Models\JournalEntry;
use App\Infrastructure\Models\JournalEntryLine;
use App\Infrastructure\Models\Product;
use App\Infrastructure\Models\ProductVariant;
use App\Infrastructure\Models\StockMovement;
use App\Infrastructure\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\AccountingContext;

afterEach(function (): void {
    app()->forgetInstance('current_tenant');
});

beforeEach(function (): void {
    $this->travelTo('2026-07-22 12:00:00');
});

function archiveInventoryFixture(Tenant $tenant): array
{
    app()->instance('current_tenant', $tenant);
    $category = Category::query()->create(['name' => 'Archive']);
    $product = Product::query()->create([
        'category_id' => $category->getKey(),
        'name' => 'Archived Widget',
        'costing_method' => 'fifo',
    ]);
    $variant = ProductVariant::query()->create([
        'product_id' => $product->getKey(),
        'sku' => 'ARCH-'.$tenant->getKey(),
        'cost_price' => '10.0000',
        'sale_price' => '15.0000',
    ]);
    $branch = Branch::factory()->create(['tenant_id' => $tenant->getKey()]);

    return compact('category', 'product', 'variant', 'branch');
}

function createArchivedStockMovement(array $attributes): StockMovement
{
    $tenantId = current_tenant_id();
    $id = DB::table('stock_movements')->insertGetId([
        'tenant_id' => $tenantId,
        'product_variant_id' => $attributes['product_variant_id'],
        'branch_id' => $attributes['branch_id'],
        'type' => $attributes['type'] instanceof BackedEnum
            ? $attributes['type']->value
            : $attributes['type'],
        'quantity_delta' => $attributes['quantity_delta'],
        'unit_cost' => $attributes['unit_cost'] ?? null,
        'source_type' => $attributes['source_type'] ?? null,
        'source_id' => $attributes['source_id'] ?? null,
        'created_at' => $attributes['created_at'],
    ]);

    return StockMovement::query()->findOrFail($id);
}

it('exports old stock movements to s3 with checksum and manifest', function () {
    Storage::fake('s3');
    config(['archive.disk' => 's3', 'archive.schema_version' => '1']);

    $tenant = Tenant::factory()->create();
    $fixture = archiveInventoryFixture($tenant);

    $old = createArchivedStockMovement([
        'product_variant_id' => $fixture['variant']->getKey(),
        'branch_id' => $fixture['branch']->getKey(),
        'type' => StockMovementType::OpeningBalance,
        'quantity_delta' => '-42.5000',
        'unit_cost' => '1.2500',
        'source_type' => null,
        'source_id' => null,
        'created_at' => '2020-06-15 12:00:00',
    ]);
    createArchivedStockMovement([
        'product_variant_id' => $fixture['variant']->getKey(),
        'branch_id' => $fixture['branch']->getKey(),
        'type' => StockMovementType::StockAdjustmentIn,
        'quantity_delta' => '1.0000',
        'unit_cost' => null,
        'source_type' => null,
        'source_id' => null,
        'created_at' => '2025-01-01 00:00:00',
    ]);

    $export = ArchiveExport::query()->create([
        'dataset' => ArchiveDataset::StockMovements,
        'period_year' => 2020,
        'schema_version' => '1',
        'status' => ArchiveExportStatus::Pending,
    ]);

    $result = app(ArchiveExportService::class)->export($export);

    expect($result->status)->toBe(ArchiveExportStatus::Completed)
        ->and($result->row_count)->toBe(1)
        ->and($result->checksum)->toBeString()->toHaveLength(64)
        ->and($result->manifest)->toMatchArray([
            'schema_version' => '1',
            'tenant_id' => $tenant->getKey(),
            'dataset' => 'stock_movements',
            'period_year' => 2020,
            'row_count' => 1,
        ]);

    $csvPath = $result->manifest['objects'][0]['path'];
    $csv = Storage::disk('s3')->get($csvPath);
    $manifest = Storage::disk('s3')->get($result->path);

    expect($csv)->toContain('id,tenant_id,product_variant_id,branch_id,type,quantity_delta,unit_cost,source_type,source_id,created_at')
        ->and($csv)->toContain((string) $old->getKey())
        ->and($csv)->toContain('-42.5000')
        ->and($csv)->toContain('1.2500')
        ->and($csv)->not->toContain('2025-01-01')
        ->and(hash('sha256', (string) $manifest))->toBe($result->checksum)
        ->and(StockMovement::query()->count())->toBe(2);
});

it('exports journal headers and related lines under one manifest', function () {
    Storage::fake('s3');
    config(['archive.disk' => 's3', 'archive.schema_version' => '1']);

    $context = AccountingContext::create('Admin');
    app()->instance('current_tenant', $context->purchasing->sales->tenant);

    $entry = JournalEntry::query()->make([
        'branch_id' => $context->purchasing->sales->branch->getKey(),
        'journal_entry_number' => 'JE-ARCH-1',
        'description' => 'Old journal',
        'reference_type' => null,
        'reference_id' => null,
        'posted_at' => '2020-03-01',
    ]);
    $entry->forceFill(['created_at' => '2020-03-01 10:00:00'])->save();

    $cash = ChartOfAccount::query()->where('code', '1100')->firstOrFail();
    $revenue = ChartOfAccount::query()->where('code', '4000')->firstOrFail();

    $debit = JournalEntryLine::query()->make([
        'journal_entry_id' => $entry->getKey(),
        'coa_id' => $cash->getKey(),
        'debit' => '10.00',
        'credit' => '0.00',
        'description' => 'Debit',
    ]);
    $debit->forceFill(['created_at' => '2020-03-01 10:00:00'])->save();

    $credit = JournalEntryLine::query()->make([
        'journal_entry_id' => $entry->getKey(),
        'coa_id' => $revenue->getKey(),
        'debit' => '0.00',
        'credit' => '10.00',
        'description' => 'Credit',
    ]);
    $credit->forceFill(['created_at' => '2020-03-01 10:00:00'])->save();

    $recent = JournalEntry::query()->make([
        'branch_id' => $context->purchasing->sales->branch->getKey(),
        'journal_entry_number' => 'JE-RECENT',
        'description' => 'Recent',
        'posted_at' => '2025-03-01',
    ]);
    $recent->forceFill(['created_at' => '2025-03-01 10:00:00'])->save();

    $export = ArchiveExport::query()->create([
        'dataset' => ArchiveDataset::JournalEntries,
        'period_year' => 2020,
        'schema_version' => '1',
        'status' => ArchiveExportStatus::Pending,
    ]);

    $result = app(ArchiveExportService::class)->export($export);
    $objectNames = array_column($result->manifest['objects'], 'name');

    expect($result->status)->toBe(ArchiveExportStatus::Completed)
        ->and($objectNames)->toBe(['headers', 'lines'])
        ->and($result->row_count)->toBe(3)
        ->and(Storage::disk('s3')->get($result->manifest['objects'][0]['path']))->toContain('JE-ARCH-1')
        ->and(Storage::disk('s3')->get($result->manifest['objects'][0]['path']))->not->toContain('JE-RECENT')
        ->and(Storage::disk('s3')->get($result->manifest['objects'][1]['path']))->toContain((string) $entry->getKey())
        ->and(Storage::disk('s3')->get($result->manifest['objects'][1]['path']))->toContain('10.00')
        ->and(JournalEntry::query()->count())->toBe(2)
        ->and(JournalEntryLine::query()->count())->toBe(2);
});

it('selects only closed calendar years older than retention', function () {
    $tenant = Tenant::factory()->create();
    $fixture = archiveInventoryFixture($tenant);

    createArchivedStockMovement([
        'product_variant_id' => $fixture['variant']->getKey(),
        'branch_id' => $fixture['branch']->getKey(),
        'type' => StockMovementType::OpeningBalance,
        'quantity_delta' => '1.0000',
        'created_at' => '2020-01-01 00:00:00',
    ]);
    createArchivedStockMovement([
        'product_variant_id' => $fixture['variant']->getKey(),
        'branch_id' => $fixture['branch']->getKey(),
        'type' => StockMovementType::OpeningBalance,
        'quantity_delta' => '1.0000',
        'created_at' => '2021-01-01 00:00:00',
    ]);
    createArchivedStockMovement([
        'product_variant_id' => $fixture['variant']->getKey(),
        'branch_id' => $fixture['branch']->getKey(),
        'type' => StockMovementType::OpeningBalance,
        'quantity_delta' => '1.0000',
        'created_at' => '2025-06-01 00:00:00',
    ]);

    $service = app(ArchiveExportService::class);
    $years = $service->discoverYearsWithData(ArchiveDataset::StockMovements);

    expect($years)->toBe([2020])
        ->and($service->maxClosedYear())->toBe(2020);
});

it('replays completed archive exports idempotently without rewriting', function () {
    Storage::fake('s3');
    config(['archive.disk' => 's3']);

    $tenant = Tenant::factory()->create();
    $fixture = archiveInventoryFixture($tenant);
    createArchivedStockMovement([
        'product_variant_id' => $fixture['variant']->getKey(),
        'branch_id' => $fixture['branch']->getKey(),
        'type' => StockMovementType::OpeningBalance,
        'quantity_delta' => '2.0000',
        'created_at' => '2020-02-02 00:00:00',
    ]);

    $export = ArchiveExport::query()->create([
        'dataset' => ArchiveDataset::StockMovements,
        'period_year' => 2020,
        'schema_version' => '1',
        'status' => ArchiveExportStatus::Pending,
    ]);

    $service = app(ArchiveExportService::class);
    $first = $service->export($export);
    $path = $first->path;
    $checksum = $first->checksum;
    Storage::disk('s3')->assertExists($path);

    $second = $service->export($export->refresh());

    expect($second->status)->toBe(ArchiveExportStatus::Completed)
        ->and($second->checksum)->toBe($checksum)
        ->and(ArchiveExport::query()->count())->toBe(1);
});

it('isolates archive exports per tenant and dispatches child jobs', function () {
    Storage::fake('s3');
    config(['archive.disk' => 's3', 'queue.default' => 'sync']);
    Queue::fake();

    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    foreach ([$tenantA, $tenantB] as $tenant) {
        $fixture = archiveInventoryFixture($tenant);
        createArchivedStockMovement([
            'product_variant_id' => $fixture['variant']->getKey(),
            'branch_id' => $fixture['branch']->getKey(),
            'type' => StockMovementType::OpeningBalance,
            'quantity_delta' => '1.0000',
            'created_at' => '2020-05-05 00:00:00',
        ]);
        $audit = AuditLog::query()->make([
            'user_id' => null,
            'action' => 'TEST',
            'entity_type' => 'product',
            'entity_id' => 1,
            'old_values' => null,
            'new_values' => ['x' => 1],
            'ip_address' => null,
            'user_agent' => null,
            'session_id' => null,
        ]);
        $audit->forceFill(['created_at' => '2020-05-05 00:00:00'])->save();
    }

    app()->forgetInstance('current_tenant');
    (new DispatchArchiveExportsJob)->handle(app(ArchiveExportService::class));

    Queue::assertPushed(ExportArchiveDatasetJob::class);

    app()->instance('current_tenant', $tenantA);
    $exportsA = ArchiveExport::query()->get();
    app()->instance('current_tenant', $tenantB);
    $exportsB = ArchiveExport::query()->get();

    expect($exportsA->pluck('tenant_id')->unique()->all())->toBe([$tenantA->getKey()])
        ->and($exportsB->pluck('tenant_id')->unique()->all())->toBe([$tenantB->getKey()])
        ->and($exportsA->where('dataset', ArchiveDataset::StockMovements)->count())->toBe(1)
        ->and($exportsB->where('dataset', ArchiveDataset::StockMovements)->count())->toBe(1);
});

it('marks failed archive exports with error codes and no exception text', function () {
    config(['archive.disk' => 'missing-disk']);

    $tenant = Tenant::factory()->create();
    archiveInventoryFixture($tenant);

    $export = ArchiveExport::query()->create([
        'dataset' => ArchiveDataset::StockMovements,
        'period_year' => 2020,
        'schema_version' => '1',
        'status' => ArchiveExportStatus::Pending,
    ]);

    $job = new ExportArchiveDatasetJob($tenant->getKey(), (int) $export->getKey());

    try {
        $job->handle(app(ArchiveExportService::class));
        expect(false)->toBeTrue();
    } catch (Throwable) {
        // Expected: storage disk missing.
    }

    $export->refresh();

    expect($export->status)->toBe(ArchiveExportStatus::Failed)
        ->and($export->error_code)->toBe('archive_export_failed')
        ->and(array_key_exists('error_message', $export->getAttributes()))->toBeFalse();
});

it('counts rfc4180 rows with embedded newlines commas and quotes', function () {
    Storage::fake('s3');
    config(['archive.disk' => 's3', 'archive.schema_version' => '1']);

    $tenant = Tenant::factory()->create();
    archiveInventoryFixture($tenant);

    $agent = "Mozilla/5.0 (compatible; \"Bot\", v1)\nnext-line, still agent";
    $audit = AuditLog::query()->make([
        'user_id' => null,
        'action' => 'NESTED_CSV',
        'entity_type' => 'product',
        'entity_id' => 99,
        'old_values' => null,
        'new_values' => [
            'note' => "line1\nline2, with \"quotes\" and, commas",
        ],
        'ip_address' => null,
        'user_agent' => $agent,
        'session_id' => null,
    ]);
    $audit->forceFill(['created_at' => '2020-08-08 08:08:08'])->save();

    $export = ArchiveExport::query()->create([
        'dataset' => ArchiveDataset::AuditLogs,
        'period_year' => 2020,
        'schema_version' => '1',
        'status' => ArchiveExportStatus::Pending,
    ]);

    $result = app(ArchiveExportService::class)->export($export);
    $csv = Storage::disk('s3')->get($result->manifest['objects'][0]['path']);

    expect($result->status)->toBe(ArchiveExportStatus::Completed)
        ->and($result->row_count)->toBe(1)
        ->and($csv)->toContain("\"Mozilla/5.0 (compatible; \"\"Bot\"\", v1)\nnext-line, still agent\"")
        ->and($csv)->toContain('line1\nline2');
});

it('claims pending exports once and no-ops fresh duplicate claims', function () {
    $tenant = Tenant::factory()->create();
    archiveInventoryFixture($tenant);

    $export = ArchiveExport::query()->create([
        'dataset' => ArchiveDataset::StockMovements,
        'period_year' => 2020,
        'schema_version' => '1',
        'status' => ArchiveExportStatus::Pending,
    ]);

    $service = app(ArchiveExportService::class);
    $first = $service->claim((int) $export->getKey());
    $startedAt = $first?->started_at?->toIso8601String();

    expect($first)->toBeInstanceOf(ArchiveExport::class)
        ->and($first->status)->toBe(ArchiveExportStatus::Exporting)
        ->and($startedAt)->not->toBeNull();

    $second = $service->claim((int) $export->getKey());

    expect($second)->toBeNull();

    $export->refresh();

    expect($export->status)->toBe(ArchiveExportStatus::Exporting)
        ->and($export->started_at?->toIso8601String())->toBe($startedAt)
        ->and(StockMovement::query()->count())->toBeGreaterThanOrEqual(0);
});

it('reclaims stale exporting and failed rows but skips completed', function () {
    config([
        'archive.export_timeout_seconds' => 60,
        'archive.export_claim_grace_seconds' => 30,
    ]);

    $tenant = Tenant::factory()->create();
    archiveInventoryFixture($tenant);
    $service = app(ArchiveExportService::class);

    $export = ArchiveExport::query()->create([
        'dataset' => ArchiveDataset::StockMovements,
        'period_year' => 2020,
        'schema_version' => '1',
        'status' => ArchiveExportStatus::Exporting,
        'started_at' => now()->subSeconds(120),
        'disk' => 's3',
        'path' => 'archives/old/manifest.json',
    ]);

    $reclaimed = $service->claim((int) $export->getKey());

    expect($reclaimed)->toBeInstanceOf(ArchiveExport::class)
        ->and($reclaimed->status)->toBe(ArchiveExportStatus::Exporting)
        ->and($reclaimed->started_at?->greaterThan(now()->subSeconds(5)))->toBeTrue();

    $export->forceFill([
        'status' => ArchiveExportStatus::Failed,
        'error_code' => 'archive_export_failed',
        'started_at' => now()->subMinute(),
    ])->save();

    expect($service->claim((int) $export->getKey()))->toBeInstanceOf(ArchiveExport::class);

    $export->forceFill([
        'status' => ArchiveExportStatus::Completed,
        'checksum' => str_repeat('a', 64),
        'row_count' => 0,
        'completed_at' => now(),
    ])->save();

    expect($service->claim((int) $export->getKey()))->toBeNull()
        ->and($export->fresh()->status)->toBe(ArchiveExportStatus::Completed);
});

it('does not let a duplicate export rewrite objects for a fresh claim holder', function () {
    Storage::fake('s3');
    config(['archive.disk' => 's3']);

    $tenant = Tenant::factory()->create();
    $fixture = archiveInventoryFixture($tenant);
    createArchivedStockMovement([
        'product_variant_id' => $fixture['variant']->getKey(),
        'branch_id' => $fixture['branch']->getKey(),
        'type' => StockMovementType::OpeningBalance,
        'quantity_delta' => '1.0000',
        'created_at' => '2020-04-01 00:00:00',
    ]);

    $export = ArchiveExport::query()->create([
        'dataset' => ArchiveDataset::StockMovements,
        'period_year' => 2020,
        'schema_version' => '1',
        'status' => ArchiveExportStatus::Pending,
    ]);

    $service = app(ArchiveExportService::class);
    $service->claim((int) $export->getKey());

    $before = Storage::disk('s3')->allFiles();
    $noop = $service->export($export->fresh());
    $after = Storage::disk('s3')->allFiles();

    expect($noop->status)->toBe(ArchiveExportStatus::Exporting)
        ->and($after)->toBe($before)
        ->and(StockMovement::query()->count())->toBe(1);
});
