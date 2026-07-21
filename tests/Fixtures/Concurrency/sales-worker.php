<?php

declare(strict_types=1);

use App\Application\Actions\Accounting\CreateManualJournalAction;
use App\Application\Actions\Accounting\LockAccountingPeriodAction;
use App\Application\Actions\Purchasing\ProcessGoodsReceiptAction;
use App\Application\Actions\Purchasing\RecordBillPaymentAction;
use App\Application\Actions\Sales\ApproveCreditNoteAction;
use App\Application\Actions\Sales\CreateInvoiceAction;
use App\Application\DTOs\BillPaymentData;
use App\Application\DTOs\GoodsReceiptData;
use App\Application\DTOs\GrnItemData;
use App\Application\DTOs\InvoiceData;
use App\Application\DTOs\InvoiceItemData;
use App\Application\DTOs\JournalEntryLineData;
use App\Application\DTOs\ManualJournalData;
use App\Application\Jobs\AggregateJournalRollupsJob;
use App\Domain\Entities\PurchasePaymentMethod;
use App\Domain\Services\InvoiceNumberService;
use App\Infrastructure\Models\Bill;
use App\Infrastructure\Models\Tenant;
use Illuminate\Contracts\Console\Kernel;

ini_set('display_errors', '0');

require dirname(__DIR__, 3).'/vendor/autoload.php';

$application = require dirname(__DIR__, 3).'/bootstrap/app.php';
$application->make(Kernel::class)->bootstrap();

/**
 * @param  array<string, mixed>  $result
 */
function respond(array $result): never
{
    fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR).PHP_EOL);
    exit($result['ok'] === true ? 0 : 1);
}

/**
 * @param  array<string, mixed>  $payload
 */
function awaitBarrier(array $payload): void
{
    $barrier = $payload['barrier'] ?? null;

    if (! is_string($barrier) || $barrier === '') {
        return;
    }

    $workerToken = $payload['worker_token'] ?? null;

    if (is_string($workerToken) && $workerToken !== '') {
        $readyFile = $barrier.'.'.$workerToken.'.ready';

        if (file_put_contents($readyFile, 'ready', LOCK_EX) === false) {
            throw new RuntimeException('Concurrency worker could not signal readiness.');
        }
    }

    $deadline = microtime(true) + 20;

    while (! is_file($barrier)) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('Concurrency barrier timed out.');
        }

        usleep(10_000);
    }
}

try {
    $mode = $argv[1] ?? '';
    $decoded = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);

    if (! is_array($decoded)) {
        throw new InvalidArgumentException('Worker payload must be a JSON object.');
    }

    $tenant = Tenant::query()->findOrFail((int) ($decoded['tenant_id'] ?? 0));
    app()->instance('current_tenant', $tenant);
    awaitBarrier($decoded);

    if ($mode === 'invoice-number') {
        $numbers = [];
        $count = (int) ($decoded['count'] ?? 1);

        for ($index = 0; $index < $count; $index++) {
            $numbers[] = app(InvoiceNumberService::class)->next((int) $decoded['year']);
        }

        respond(['ok' => true, 'numbers' => $numbers]);
    }

    if ($mode === 'create-invoice') {
        $items = array_map(
            static fn (array $item): InvoiceItemData => new InvoiceItemData(
                (int) $item['variant_id'],
                (string) $item['quantity'],
                (string) $item['unit_price'],
                isset($item['tax_id']) ? (int) $item['tax_id'] : null,
            ),
            $decoded['items'],
        );
        $invoiceData = new InvoiceData(
            (int) $decoded['branch_id'],
            (int) $decoded['customer_id'],
            new DateTimeImmutable((string) $decoded['invoice_date']),
            null,
            null,
            $items,
        );
        $invoiceIds = [];
        $invoiceNumbers = [];

        for ($index = 0, $count = (int) ($decoded['count'] ?? 1); $index < $count; $index++) {
            $invoice = app(CreateInvoiceAction::class)->handle($invoiceData, (int) $decoded['user_id']);
            $invoiceIds[] = $invoice->getKey();
            $invoiceNumbers[] = $invoice->invoice_number;
        }

        respond([
            'ok' => true,
            'invoice_id' => $invoiceIds[0] ?? null,
            'invoice_number' => $invoiceNumbers[0] ?? null,
            'invoice_ids' => $invoiceIds,
            'invoice_numbers' => $invoiceNumbers,
        ]);
    }

    if ($mode === 'process-grn') {
        $items = array_map(
            static fn (array $item): GrnItemData => new GrnItemData(
                (int) $item['variant_id'],
                (string) $item['quantity'],
                (string) $item['unit_cost'],
            ),
            $decoded['items'],
        );
        $receipt = app(ProcessGoodsReceiptAction::class)->handle(
            new GoodsReceiptData(
                (int) $decoded['branch_id'],
                (int) $decoded['supplier_id'],
                isset($decoded['purchase_order_id']) ? (int) $decoded['purchase_order_id'] : null,
                new DateTimeImmutable((string) $decoded['received_at']),
                isset($decoded['notes']) ? (string) $decoded['notes'] : null,
                (string) $decoded['idempotency_key'],
                (string) $decoded['payload_hash'],
                $items,
            ),
            (int) $decoded['user_id'],
        );

        respond([
            'ok' => true,
            'grn_id' => $receipt->getKey(),
            'grn_number' => $receipt->grn_number,
        ]);
    }

    if ($mode === 'pay-bill') {
        $payment = app(RecordBillPaymentAction::class)->handle(
            (int) $decoded['bill_id'],
            new BillPaymentData(
                (string) $decoded['amount'],
                PurchasePaymentMethod::from((string) $decoded['payment_method']),
                new DateTimeImmutable((string) $decoded['payment_date']),
                isset($decoded['reference']) ? (string) $decoded['reference'] : null,
            ),
            (int) $decoded['user_id'],
        );
        $billBalance = Bill::query()->findOrFail((int) $decoded['bill_id'])->balance_due;

        respond([
            'ok' => true,
            'payment_id' => $payment->getKey(),
            'bill_balance' => $billBalance,
        ]);
    }

    if ($mode === 'approve-credit') {
        $creditNote = app(ApproveCreditNoteAction::class)->handle(
            (int) $decoded['credit_note_id'],
            (int) $decoded['user_id'],
        );

        respond(['ok' => true, 'credit_note_id' => $creditNote->getKey()]);
    }

    if ($mode === 'create-manual-journal') {
        $lines = array_map(
            static fn (array $line): JournalEntryLineData => new JournalEntryLineData(
                (int) $line['coa_id'],
                (string) $line['debit'],
                (string) $line['credit'],
                isset($line['description']) ? (string) $line['description'] : null,
            ),
            $decoded['lines'],
        );
        $journal = app(CreateManualJournalAction::class)->handle(
            new ManualJournalData(
                (int) $decoded['branch_id'],
                new DateTimeImmutable((string) $decoded['posted_at']),
                (string) $decoded['description'],
                $lines,
            ),
            (int) $decoded['user_id'],
        );

        respond([
            'ok' => true,
            'journal_entry_id' => $journal->getKey(),
            'journal_entry_number' => $journal->journal_entry_number,
        ]);
    }

    if ($mode === 'lock-accounting-period') {
        $period = app(LockAccountingPeriodAction::class)->handle(
            (int) $decoded['period_id'],
            (int) $decoded['user_id'],
        );

        respond([
            'ok' => true,
            'accounting_period_id' => $period->getKey(),
            'is_locked' => $period->is_locked,
        ]);
    }

    if ($mode === 'aggregate-journal-rollup') {
        (new AggregateJournalRollupsJob(
            (int) $decoded['tenant_id'],
            (string) $decoded['date'],
        ))->handle();

        respond(['ok' => true]);
    }

    throw new InvalidArgumentException("Unknown worker mode: {$mode}");
} catch (Throwable $exception) {
    respond([
        'ok' => false,
        'error' => $exception::class,
        'message' => $exception->getMessage(),
    ]);
}
