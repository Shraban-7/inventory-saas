<?php

use App\Infrastructure\Models\Invoice;
use App\Infrastructure\Models\JournalEntry;
use App\Infrastructure\Models\StockLevel;
use App\Infrastructure\Models\StockMovement;
use Illuminate\Support\Str;
use Tests\Support\SalesContext;

afterEach(fn () => app()->forgetInstance('current_tenant'));

function createInvoiceThroughApi(SalesContext $context, $test): int
{
    return (int) $test->actingAs($context->user)
        ->postJson('/api/v1/invoices', $context->invoicePayload(), ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()
        ->json('data.id');
}

it('cursor paginates and filters invoices by status date and customer', function () {
    $context = SalesContext::create();
    foreach ([
        ['number' => 'INV-2026-10001', 'date' => '2026-07-01', 'status' => 'issued'],
        ['number' => 'INV-2026-10002', 'date' => '2026-07-15', 'status' => 'paid'],
        ['number' => 'INV-2026-10003', 'date' => '2026-08-01', 'status' => 'issued'],
    ] as $row) {
        Invoice::query()->create([
            'branch_id' => $context->branch->getKey(),
            'customer_id' => $context->customer->getKey(),
            'invoice_number' => $row['number'],
            'invoice_date' => $row['date'],
            'status' => $row['status'],
            'total_amount' => '10.00',
            'tax_amount' => '0.00',
            'balance_due' => $row['status'] === 'paid' ? '0.00' : '10.00',
        ]);
    }

    $response = $this->actingAs($context->user)->getJson(
        "/api/v1/invoices?status=issued&date_from=2026-07-01&date_to=2026-07-31&customer_id={$context->customer->getKey()}&per_page=1",
    )->assertSuccessful()->assertJsonCount(1, 'data');

    expect($response->json('data.0.invoice_number'))->toBe('INV-2026-10001')
        ->and($response->json('meta.path'))->toContain('/api/v1/invoices');
});

it('renders validation failures as RFC 7807 problem details', function () {
    $context = SalesContext::create();

    $this->actingAs($context->user)
        ->postJson('/api/v1/invoices', [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', 'urn:problem:validation')
        ->assertJsonPath('status', 422)
        ->assertJsonStructure(['type', 'title', 'status', 'detail', 'instance', 'errors' => ['branch_id']]);
});

it('records partial and full receipts with balanced journals and rejects overpayment', function () {
    $context = SalesContext::create();
    $invoiceId = createInvoiceThroughApi($context, $this);
    $receipt = fn (string $amount): array => [
        'amount' => $amount,
        'payment_method' => 'cash',
        'payment_date' => '2026-07-22',
        'reference' => 'PAY-'.$amount,
    ];

    $this->postJson("/api/v1/invoices/{$invoiceId}/receipts", $receipt('10.00'), ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated();
    expect(Invoice::query()->findOrFail($invoiceId)->getRawOriginal('status'))->toBe('partially_paid')
        ->and(Invoice::query()->findOrFail($invoiceId)->balance_due)->toBe('11.50');

    $this->postJson("/api/v1/invoices/{$invoiceId}/receipts", $receipt('12.00'), ['Idempotency-Key' => (string) Str::uuid()])
        ->assertUnprocessable();
    $this->postJson("/api/v1/invoices/{$invoiceId}/receipts", $receipt('11.50'), ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated();

    $invoice = Invoice::query()->findOrFail($invoiceId);
    expect($invoice->getRawOriginal('status'))->toBe('paid')
        ->and($invoice->balance_due)->toBe('0.00')
        ->and(JournalEntry::query()->count())->toBe(3);

    foreach (JournalEntry::query()->with('lines')->get() as $journal) {
        expect((float) $journal->lines->sum('debit'))->toBe((float) $journal->lines->sum('credit'));
    }

    $this->putJson("/api/v1/invoices/{$invoiceId}/void", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertUnprocessable();
});

it('voids an issued invoice by restoring stock and posting a balanced reversal', function () {
    $context = SalesContext::create();
    $invoiceId = createInvoiceThroughApi($context, $this);

    $this->putJson("/api/v1/invoices/{$invoiceId}/void", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'voided')
        ->assertJsonPath('data.balance_due', '0.00');

    expect(StockLevel::query()->where('product_variant_id', $context->variant->getKey())->value('quantity_on_hand'))->toBe('20.0000')
        ->and(StockMovement::query()->where('type', 'SALES_RETURN')->count())->toBe(1)
        ->and(JournalEntry::query()->count())->toBe(2);

    $reversal = JournalEntry::query()->where('journal_entry_number', "JRN-VOID-{$invoiceId}")->firstOrFail();
    expect((float) $reversal->lines()->sum('debit'))->toBe((float) $reversal->lines()->sum('credit'));
});
