<?php

use App\Infrastructure\Models\Customer;
use Illuminate\Support\Facades\DB;
use Tests\Support\SalesContext;

afterEach(fn () => app()->forgetInstance('current_tenant'));

it('creates reads updates and paginates tenant customers with encrypted address round trips', function () {
    $context = SalesContext::create();
    $this->actingAs($context->user);
    $payload = [
        'default_branch_id' => $context->branch->getKey(),
        'name' => 'Acme Retail',
        'email' => 'buyer@example.test',
        'phone' => '+1-555-0100',
        'address' => ['line_1' => '42 Market Street', 'city' => 'Dhaka'],
    ];

    $created = $this->postJson('/api/v1/customers', $payload)
        ->assertCreated()
        ->assertJsonPath('data.default_branch_id', $context->branch->getKey())
        ->assertJsonPath('data.address.city', 'Dhaka');
    $customerId = $created->json('data.id');
    $raw = DB::table((new Customer)->getTable())->where('id', $customerId)->first();

    expect($raw->address)->not->toContain('Market Street')
        ->and($raw->email)->not->toContain('buyer@example.test');

    $this->getJson("/api/v1/customers/{$customerId}")
        ->assertSuccessful()
        ->assertJsonPath('data.email', 'buyer@example.test')
        ->assertJsonPath('data.address.line_1', '42 Market Street');

    $this->putJson("/api/v1/customers/{$customerId}", [...$payload, 'name' => 'Acme Updated'])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'Acme Updated');

    Customer::factory()->count(3)->create(['tenant_id' => $context->tenant->getKey()]);
    $this->getJson('/api/v1/customers?per_page=2')
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.per_page', 2);
});

it('soft deletes customers at the model boundary when no delete endpoint exists', function () {
    $context = SalesContext::create();
    $customer = Customer::factory()->create(['tenant_id' => $context->tenant->getKey()]);

    $customer->delete();

    expect(Customer::query()->find($customer->getKey()))->toBeNull()
        ->and(Customer::withTrashed()->find($customer->getKey())?->trashed())->toBeTrue();
});

it('rejects a default branch owned by another tenant', function () {
    $context = SalesContext::create();
    $foreign = SalesContext::create();

    $this->actingAs($context->user)->postJson('/api/v1/customers', [
        'default_branch_id' => $foreign->branch->getKey(),
        'name' => 'Foreign branch customer',
    ])->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/problem+json');
});
