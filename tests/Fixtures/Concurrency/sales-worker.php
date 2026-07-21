<?php

declare(strict_types=1);

use App\Application\Actions\Sales\ApproveCreditNoteAction;
use App\Application\Actions\Sales\CreateInvoiceAction;
use App\Application\DTOs\InvoiceData;
use App\Application\DTOs\InvoiceItemData;
use App\Domain\Services\InvoiceNumberService;
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

    if ($mode === 'approve-credit') {
        $creditNote = app(ApproveCreditNoteAction::class)->handle(
            (int) $decoded['credit_note_id'],
            (int) $decoded['user_id'],
        );

        respond(['ok' => true, 'credit_note_id' => $creditNote->getKey()]);
    }

    throw new InvalidArgumentException("Unknown worker mode: {$mode}");
} catch (Throwable $exception) {
    respond([
        'ok' => false,
        'error' => $exception::class,
        'message' => $exception->getMessage(),
    ]);
}
