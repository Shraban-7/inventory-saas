<?php

use App\Infrastructure\Models\Branch;
use App\Infrastructure\Models\Tenant;
use App\Infrastructure\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Route::middleware(['auth', 'tenant', 'idempotency'])
        ->post('/api/test/financial-operation', function (Request $request) {
            $branch = Branch::query()->create([
                'name' => $request->string('name')->toString(),
            ]);

            return response()->json([
                'id' => $branch->getKey(),
                'name' => $branch->name,
            ], 201);
        });
});

it('replays an identical response without duplicating the write', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->getKey()]);
    $key = (string) Str::uuid();

    $first = $this->actingAs($user)->postJson(
        '/api/test/financial-operation',
        ['name' => 'Main'],
        ['Idempotency-Key' => $key],
    )->assertCreated();

    $second = $this->actingAs($user)->postJson(
        '/api/test/financial-operation',
        ['name' => 'Main'],
        ['Idempotency-Key' => $key],
    )->assertCreated();

    app()->instance('current_tenant', $tenant);

    expect($second->getContent())->toBe($first->getContent())
        ->and(Branch::query()->count())->toBe(1);
});

it('rejects reuse of a key with a different payload', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->getKey()]);
    $key = (string) Str::uuid();

    $this->actingAs($user)->postJson(
        '/api/test/financial-operation',
        ['name' => 'Main'],
        ['Idempotency-Key' => $key],
    )->assertCreated();

    $this->actingAs($user)->postJson(
        '/api/test/financial-operation',
        ['name' => 'Other'],
        ['Idempotency-Key' => $key],
    )->assertConflict()
        ->assertJsonPath('title', 'Idempotency conflict');
});

it('requires a UUID idempotency key', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->getKey()]);

    $this->actingAs($user)->postJson(
        '/api/test/financial-operation',
        ['name' => 'Main'],
    )->assertBadRequest();
});
