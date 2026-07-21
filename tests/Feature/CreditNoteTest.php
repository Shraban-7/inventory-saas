<?php

use App\Infrastructure\Models\CreditNote;
use App\Infrastructure\Models\JournalEntry;
use App\Infrastructure\Models\StockLevel;
use App\Infrastructure\Models\StockMovement;
use Illuminate\Support\Str;
use Tests\Support\SalesContext;

afterEach(fn () => app()->forgetInstance('current_tenant'));

function approvedSaleInvoiceId(SalesContext $context, $test): int
{
    return (int) $test->actingAs($context->user)
        ->postJson('/api/v1/invoices', $context->invoicePayload(), ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()
        ->json('data.id');
}

function linkedCreditPayload(SalesContext $context, int $invoiceId, string $quantity = '1.0000'): array
{
    return [
        'branch_id' => $context->branch->getKey(),
        'customer_id' => $context->customer->getKey(),
        'invoice_id' => $invoiceId,
        'reason' => 'Customer return',
        'items' => [[
            'variant_id' => $context->variant->getKey(),
            'quantity' => $quantity,
        ]],
    ];
}

it('creates a draft and approves it once with stock return and balanced reversal', function () {
    $context = SalesContext::create();
    $invoiceId = approvedSaleInvoiceId($context, $this);
    $creditId = (int) $this->postJson(
        '/api/v1/credit-notes',
        linkedCreditPayload($context, $invoiceId),
        ['Idempotency-Key' => (string) Str::uuid()],
    )->assertCreated()->assertJsonPath('data.status', 'draft')->json('data.id');

    $this->putJson("/api/v1/credit-notes/{$creditId}/approve", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'approved');

    expect(StockLevel::query()->where('product_variant_id', $context->variant->getKey())->value('quantity_on_hand'))->toBe('19.0000')
        ->and(StockMovement::query()->where('type', 'SALES_RETURN')->count())->toBe(1)
        ->and(JournalEntry::query()->count())->toBe(2);

    $journal = JournalEntry::query()->where('reference_type', 'credit_note')->where('reference_id', $creditId)->firstOrFail();
    expect((float) $journal->lines()->sum('debit'))->toBe((float) $journal->lines()->sum('credit'));

    $this->putJson("/api/v1/credit-notes/{$creditId}/approve", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertUnprocessable();
    expect(StockMovement::query()->where('type', 'SALES_RETURN')->count())->toBe(1)
        ->and(JournalEntry::query()->where('reference_type', 'credit_note')->count())->toBe(1);
});

it('caps cumulative linked return quantities at the quantity sold', function () {
    $context = SalesContext::create();
    $invoiceId = approvedSaleInvoiceId($context, $this);

    $this->postJson(
        '/api/v1/credit-notes',
        linkedCreditPayload($context, $invoiceId, '2.0000'),
        ['Idempotency-Key' => (string) Str::uuid()],
    )->assertCreated();

    $this->postJson(
        '/api/v1/credit-notes',
        linkedCreditPayload($context, $invoiceId, '0.0001'),
        ['Idempotency-Key' => (string) Str::uuid()],
    )->assertUnprocessable();

    expect(CreditNote::query()->count())->toBe(1);
});
