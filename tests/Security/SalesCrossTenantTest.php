<?php

use App\Infrastructure\Models\CreditNote;
use App\Infrastructure\Models\Invoice;
use Illuminate\Support\Str;
use Tests\Support\SalesContext;

afterEach(fn () => app()->forgetInstance('current_tenant'));

function directTenantInvoice(SalesContext $context, string $number): Invoice
{
    return Invoice::query()->create([
        'branch_id' => $context->branch->getKey(),
        'customer_id' => $context->customer->getKey(),
        'invoice_number' => $number,
        'invoice_date' => '2026-07-22',
        'total_amount' => '10.00',
        'tax_amount' => '0.00',
        'balance_due' => '10.00',
    ]);
}

it('hides foreign customers from show update and tenant lists', function () {
    $tenantA = SalesContext::create();
    $tenantB = SalesContext::create();

    $this->actingAs($tenantA->user)
        ->getJson("/api/v1/customers/{$tenantB->customer->getKey()}")
        ->assertNotFound();
    $this->putJson("/api/v1/customers/{$tenantB->customer->getKey()}", ['name' => 'Hijacked'])
        ->assertNotFound();
    $this->getJson('/api/v1/customers')
        ->assertSuccessful()
        ->assertJsonMissing(['id' => $tenantB->customer->getKey()])
        ->assertJsonFragment(['id' => $tenantA->customer->getKey()]);
});

it('rejects every foreign sales identifier during validation', function (string $field) {
    $tenantA = SalesContext::create();
    $tenantB = SalesContext::create();
    $payload = $tenantA->invoicePayload();

    if ($field === 'branch_id') {
        $payload['branch_id'] = $tenantB->branch->getKey();
    }
    if ($field === 'customer_id') {
        $payload['customer_id'] = $tenantB->customer->getKey();
    }

    if ($field === 'variant_id') {
        $payload['items'][0]['variant_id'] = $tenantB->variant->getKey();
    }
    if ($field === 'tax_id') {
        $payload['items'][0]['tax_id'] = $tenantB->tax->getKey();
    }

    $this->actingAs($tenantA->user)
        ->postJson('/api/v1/invoices', $payload, ['Idempotency-Key' => (string) Str::uuid()])
        ->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/problem+json');
})->with(['foreign branch' => ['branch_id'], 'foreign customer' => ['customer_id'], 'foreign variant' => ['variant_id'], 'foreign tax' => ['tax_id']]);

it('cannot show void pay or approve another tenants sales records and lists exclude them', function () {
    $tenantA = SalesContext::create();
    $invoiceA = directTenantInvoice($tenantA, 'INV-2026-70001');
    $tenantB = SalesContext::create();
    $invoiceB = directTenantInvoice($tenantB, 'INV-2026-70001');
    $creditB = CreditNote::query()->create([
        'branch_id' => $tenantB->branch->getKey(),
        'customer_id' => $tenantB->customer->getKey(),
        'invoice_id' => $invoiceB->getKey(),
        'reason' => 'Foreign return',
        'total_amount' => '1.00',
    ]);

    $this->actingAs($tenantA->user)
        ->getJson("/api/v1/invoices/{$invoiceB->getKey()}")
        ->assertNotFound();
    $this->putJson("/api/v1/invoices/{$invoiceB->getKey()}/void", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertNotFound();
    $this->postJson("/api/v1/invoices/{$invoiceB->getKey()}/receipts", [
        'amount' => '1.00',
        'payment_method' => 'cash',
        'payment_date' => '2026-07-22',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertNotFound();
    $this->putJson("/api/v1/credit-notes/{$creditB->getKey()}/approve", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertNotFound();

    $this->getJson('/api/v1/invoices')
        ->assertSuccessful()
        ->assertJsonFragment(['id' => $invoiceA->getKey()])
        ->assertJsonMissing(['id' => $invoiceB->getKey()]);
    $this->getJson('/api/v1/credit-notes')
        ->assertSuccessful()
        ->assertJsonMissing(['id' => $creditB->getKey()]);
});
