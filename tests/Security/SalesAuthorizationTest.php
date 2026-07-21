<?php

use App\Infrastructure\Models\CreditNote;
use App\Infrastructure\Models\Role;
use Illuminate\Support\Str;
use Tests\Support\SalesContext;

afterEach(fn () => app()->forgetInstance('current_tenant'));

it('allows cashiers to create and view invoices but forbids void and credit approval', function () {
    $context = SalesContext::create('Cashier');
    $invoiceId = (int) $this->actingAs($context->user)
        ->postJson('/api/v1/invoices', $context->invoicePayload(), ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()
        ->json('data.id');

    $this->getJson("/api/v1/invoices/{$invoiceId}")->assertSuccessful();
    $this->putJson("/api/v1/invoices/{$invoiceId}/void", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertForbidden()
        ->assertHeader('Content-Type', 'application/problem+json');

    app()->instance('current_tenant', $context->tenant);
    $credit = CreditNote::query()->create([
        'branch_id' => $context->branch->getKey(),
        'customer_id' => $context->customer->getKey(),
        'invoice_id' => $invoiceId,
        'reason' => 'Cashier cannot approve',
        'total_amount' => '1.00',
    ]);
    $this->putJson("/api/v1/credit-notes/{$credit->getKey()}/approve", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertForbidden();
});

it('forbids a branch-scoped user from creating or viewing another branch invoice', function () {
    $context = SalesContext::create();
    $context->user->roles()->detach();
    $cashier = Role::query()->where('name', 'Cashier')->firstOrFail();
    $context->user->roles()->attach($cashier->getKey(), ['branch_id' => $context->branch->getKey()]);

    $payload = $context->invoicePayload();
    $payload['branch_id'] = $context->otherBranch->getKey();

    $this->actingAs($context->user)
        ->postJson('/api/v1/invoices', $payload, ['Idempotency-Key' => (string) Str::uuid()])
        ->assertForbidden();

    $payload['branch_id'] = $context->branch->getKey();
    $invoiceId = (int) $this->postJson('/api/v1/invoices', $payload, ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()
        ->json('data.id');

    $context->user->roles()->updateExistingPivot($cashier->getKey(), ['branch_id' => $context->otherBranch->getKey()]);
    $context->user->unsetRelation('roles');
    $this->getJson("/api/v1/invoices/{$invoiceId}")->assertForbidden();
});

it('requires authentication on sales routes', function (string $method, string $uri) {
    SalesContext::create();

    $this->json($method, $uri, [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/problem+json');
})->with([
    'customers list' => ['GET', '/api/v1/customers'],
    'invoices list' => ['GET', '/api/v1/invoices'],
    'invoice create' => ['POST', '/api/v1/invoices'],
    'credit notes list' => ['GET', '/api/v1/credit-notes'],
]);
