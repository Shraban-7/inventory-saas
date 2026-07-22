<?php

use App\Application\Contracts\DnsResolver;
use App\Infrastructure\Models\Role;
use App\Infrastructure\Models\WebhookEndpoint;
use Illuminate\Support\Facades\DB;
use Tests\Support\SalesContext;

afterEach(fn () => app()->forgetInstance('current_tenant'));

beforeEach(function (): void {
    app()->instance(DnsResolver::class, new class implements DnsResolver
    {
        public function resolve(string $host): array
        {
            return ['93.184.216.34'];
        }
    });
});

it('lets only tenant-wide admins manage endpoints and reveals the secret once', function () {
    $context = SalesContext::create();
    $response = $this->actingAs($context->user)->postJson('/api/v1/webhooks', [
        'url' => 'https://example.com/inventory',
        'events' => ['invoice.created', 'grn.processed'],
    ])->assertCreated()
        ->assertJsonPath('data.url', 'https://example.com/inventory')
        ->assertJsonStructure(['data' => ['id', 'secret']]);

    $endpointId = $response->json('data.id');
    $secret = $response->json('data.secret');
    $storedSecret = DB::table((new WebhookEndpoint)->getTable())
        ->where('id', $endpointId)
        ->value('secret');

    expect($storedSecret)->not->toBe($secret);

    $this->getJson('/api/v1/webhooks')
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', $endpointId)
        ->assertJsonMissing(['secret' => $secret]);

    $context->user->roles()->detach();
    $manager = Role::query()->where('name', 'Manager')->firstOrFail();
    $context->user->roles()->attach($manager->getKey());
    $this->getJson('/api/v1/webhooks')->assertForbidden();

    $context->user->roles()->detach();
    $admin = Role::query()->where('name', 'Admin')->firstOrFail();
    $context->user->roles()->attach($admin->getKey(), [
        'branch_id' => $context->branch->getKey(),
    ]);
    $this->getJson('/api/v1/webhooks')->assertForbidden();
    $this->postJson('/api/v1/webhooks', [
        'url' => 'https://example.com/forbidden',
        'events' => ['invoice.created'],
    ])->assertForbidden();
});

it('blocks private and metadata webhook destinations', function (string $url) {
    $context = SalesContext::create();

    $this->actingAs($context->user)->postJson('/api/v1/webhooks', [
        'url' => $url,
        'events' => ['invoice.created'],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('url');
})->with([
    'loopback' => ['http://127.0.0.1/hook'],
    'private network' => ['https://10.0.0.2/hook'],
    'link local metadata' => ['https://169.254.169.254/latest/meta-data'],
    'localhost name' => ['https://localhost/hook'],
]);

it('isolates endpoints by tenant and deactivates instead of deleting', function () {
    $tenantA = SalesContext::create();
    $endpointA = WebhookEndpoint::query()->create([
        'url' => 'https://example.com/a',
        'secret' => 'secret-a',
        'events' => ['invoice.created'],
    ]);
    $tenantB = SalesContext::create();
    $endpointB = WebhookEndpoint::query()->create([
        'url' => 'https://example.com/b',
        'secret' => 'secret-b',
        'events' => ['invoice.created'],
    ]);

    $this->actingAs($tenantA->user)
        ->getJson('/api/v1/webhooks')
        ->assertSuccessful()
        ->assertJsonFragment(['id' => $endpointA->getKey()])
        ->assertJsonMissing(['id' => $endpointB->getKey()]);
    $this->deleteJson("/api/v1/webhooks/{$endpointB->getKey()}")->assertNotFound();
    $this->deleteJson("/api/v1/webhooks/{$endpointA->getKey()}")->assertNoContent();

    app()->instance('current_tenant', $tenantA->tenant);
    expect($endpointA->fresh())
        ->is_active->toBeFalse()
        ->deactivated_at->not->toBeNull();

    $this->actingAs($tenantA->user)
        ->getJson('/api/v1/webhooks')
        ->assertSuccessful()
        ->assertJsonMissing(['id' => $endpointA->getKey()]);
});
