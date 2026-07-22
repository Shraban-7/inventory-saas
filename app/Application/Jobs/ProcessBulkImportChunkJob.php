<?php

namespace App\Application\Jobs;

use App\Application\Actions\Inventory\AdjustStockAction;
use App\Application\Actions\Inventory\CreateProductAction;
use App\Application\DTOs\ProductData;
use App\Application\DTOs\StockAdjustmentData;
use App\Application\Jobs\Concerns\RestoresTenantContext;
use App\Application\Services\BranchAuthorizationService;
use App\Application\Services\CanonicalJson;
use App\Domain\Entities\BulkImportRowStatus;
use App\Domain\Entities\BulkImportType;
use App\Domain\Entities\CostingMethod;
use App\Domain\Entities\Quantity;
use App\Domain\Entities\StockMovementType;
use App\Domain\Exceptions\IdempotencyConflictException;
use App\Infrastructure\Models\BulkImport;
use App\Infrastructure\Models\BulkImportRow;
use App\Infrastructure\Models\ProductVariant;
use App\Infrastructure\Models\User;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProcessBulkImportChunkJob implements ShouldQueue
{
    use Batchable, Queueable, RestoresTenantContext;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [5, 15, 30];

    public int $timeout = 60;

    /**
     * @param  list<array{row_id: int, row_number: int, values: list<string|null>}>  $rows
     * @param  list<string>  $headers
     */
    public function __construct(
        public readonly int $tenantId,
        public readonly string $importId,
        public readonly int $requestedByUserId,
        public readonly string $type,
        public readonly array $headers,
        public readonly array $rows,
    ) {
        $this->onQueue('imports');
        $this->afterCommit();
    }

    public function handle(
        CreateProductAction $createProduct,
        AdjustStockAction $adjustStock,
        BranchAuthorizationService $authorization,
        CanonicalJson $canonicalJson,
    ): void {
        $this->withinTenant(function () use ($createProduct, $adjustStock, $authorization, $canonicalJson): void {
            BulkImport::query()->findOrFail($this->importId);
            $user = User::query()->findOrFail($this->requestedByUserId);
            $type = BulkImportType::from($this->type);

            foreach ($this->rows as $payload) {
                $row = BulkImportRow::query()->findOrFail($payload['row_id']);

                if ($row->getRawOriginal('status') !== BulkImportRowStatus::Pending->value) {
                    continue;
                }

                try {
                    if (count($payload['values']) !== count($this->headers)) {
                        throw ValidationException::withMessages([
                            'row' => 'The row column count does not match the CSV header.',
                        ]);
                    }

                    $data = array_combine($this->headers, $payload['values']);

                    $status = match ($type) {
                        BulkImportType::Products => $this->processProduct($row, $data, $createProduct),
                        BulkImportType::StockAdjustments => $this->processStockAdjustment(
                            $data,
                            $user,
                            $adjustStock,
                            $authorization,
                            $canonicalJson,
                        ),
                    };
                    $this->finishRow($row, $status);
                } catch (ValidationException $exception) {
                    $message = collect($exception->errors())->flatten()->implode(' ');
                    $this->finishRow(
                        $row,
                        BulkImportRowStatus::Failed,
                        'validation_failed',
                        Str::limit($message, 1000),
                    );
                } catch (IdempotencyConflictException) {
                    $this->finishRow(
                        $row,
                        BulkImportRowStatus::Failed,
                        'idempotency_conflict',
                        'The idempotency key was already used for a different stock adjustment.',
                    );
                } catch (Throwable) {
                    $this->finishRow(
                        $row,
                        BulkImportRowStatus::Failed,
                        'row_processing_failed',
                        'The row could not be processed.',
                    );
                }
            }
        });
    }

    public function failed(?Throwable $exception): void
    {
        $this->withinTenant(function (): void {
            foreach ($this->rows as $payload) {
                $row = BulkImportRow::query()->find($payload['row_id']);

                if ($row instanceof BulkImportRow
                    && $row->getRawOriginal('status') === BulkImportRowStatus::Pending->value) {
                    $this->finishRow(
                        $row,
                        BulkImportRowStatus::Failed,
                        'chunk_processing_failed',
                        'The row could not be processed because its import chunk failed.',
                    );
                }
            }
        });
    }

    /** @param array<string, string|null> $data */
    private function processProduct(
        BulkImportRow $row,
        array $data,
        CreateProductAction $action,
    ): BulkImportRowStatus {
        $validated = Validator::make($data, [
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('tenant_id', $this->tenantId)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'costing_method' => ['required', new Enum(CostingMethod::class)],
            'sku' => ['required', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'cost_price' => ['required', 'numeric', 'decimal:0,4', 'gte:0'],
            'sale_price' => ['required', 'numeric', 'decimal:0,4', 'gte:0'],
            'reorder_point' => ['required', 'integer', 'min:0'],
        ])->validate();

        $sku = (string) $validated['sku'];

        if (ProductVariant::query()->where('sku', $sku)->exists()) {
            return $row->target_key === $sku
                ? BulkImportRowStatus::Succeeded
                : BulkImportRowStatus::Skipped;
        }

        try {
            DB::transaction(function () use ($action, $row, $sku, $validated): void {
                $action->handle(new ProductData(
                    (int) $validated['category_id'],
                    (string) $validated['name'],
                    ($validated['description'] ?? '') === '' ? null : (string) $validated['description'],
                    CostingMethod::from((string) $validated['costing_method']),
                    [[
                        'sku' => $sku,
                        'barcode' => ($validated['barcode'] ?? '') === '' ? null : (string) $validated['barcode'],
                        'cost_price' => (string) $validated['cost_price'],
                        'sale_price' => (string) $validated['sale_price'],
                        'reorder_point' => (int) $validated['reorder_point'],
                    ]],
                ));

                $row->forceFill(['target_key' => $sku])->save();
            });
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                return BulkImportRowStatus::Skipped;
            }

            throw $exception;
        }

        $row->refresh();

        return BulkImportRowStatus::Succeeded;
    }

    /**
     * @param  array<string, string|null>  $data
     */
    private function processStockAdjustment(
        array $data,
        User $user,
        AdjustStockAction $action,
        BranchAuthorizationService $authorization,
        CanonicalJson $canonicalJson,
    ): BulkImportRowStatus {
        $validated = Validator::make($data, [
            'variant_id' => ['required', 'integer', Rule::exists('product_variants', 'id')->where('tenant_id', $this->tenantId)],
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $this->tenantId)],
            'quantity_delta' => ['required', 'numeric', 'decimal:0,4', 'not_in:0,0.0,0.0000'],
            'type' => ['required', new Enum(StockMovementType::class), Rule::in([
                StockMovementType::StockAdjustmentIn->value,
                StockMovementType::StockAdjustmentOut->value,
            ])],
            'reason' => ['required', 'string', 'max:255'],
            'idempotency_key' => ['required', 'uuid'],
        ])->validate();

        $branchId = (int) $validated['branch_id'];

        if (! $authorization->allows($user, 'stock.adjust', [$branchId])) {
            throw ValidationException::withMessages([
                'branch_id' => 'The user is not authorized for this branch.',
            ]);
        }

        $type = StockMovementType::from((string) $validated['type']);
        $delta = (string) $validated['quantity_delta'];

        if (Quantity::from($delta)->isPositive() !== ($type === StockMovementType::StockAdjustmentIn)) {
            throw ValidationException::withMessages([
                'type' => 'The adjustment type must match the quantity direction.',
            ]);
        }

        $payload = [
            'variant_id' => (int) $validated['variant_id'],
            'branch_id' => $branchId,
            'quantity_delta' => $delta,
            'type' => $type->value,
            'reason' => (string) $validated['reason'],
        ];
        $action->handle(new StockAdjustmentData(
            $payload['variant_id'],
            $payload['branch_id'],
            $payload['quantity_delta'],
            $payload['reason'],
            $type,
            (string) $validated['idempotency_key'],
            hash('sha256', $canonicalJson->encode($payload)),
            (int) $user->getKey(),
        ));

        return BulkImportRowStatus::Succeeded;
    }

    private function finishRow(
        BulkImportRow $row,
        BulkImportRowStatus $status,
        ?string $errorCode = null,
        ?string $errorMessage = null,
    ): void {
        DB::transaction(function () use ($errorCode, $errorMessage, $row, $status): void {
            $lockedRow = BulkImportRow::query()
                ->whereKey($row->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRow->getRawOriginal('status') !== BulkImportRowStatus::Pending->value) {
                return;
            }

            $lockedRow->forceFill([
                'status' => $status,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
            ])->save();

            $import = BulkImport::query()->lockForUpdate()->findOrFail($this->importId);
            $attribute = match ($status) {
                BulkImportRowStatus::Succeeded => 'succeeded_rows',
                BulkImportRowStatus::Failed => 'failed_rows',
                BulkImportRowStatus::Skipped => 'skipped_rows',
                BulkImportRowStatus::Pending => null,
            };

            if ($attribute !== null) {
                $import->increment($attribute);
                $import->increment('processed_rows');
            }
        });
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        return in_array((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '23505'], true);
    }
}
