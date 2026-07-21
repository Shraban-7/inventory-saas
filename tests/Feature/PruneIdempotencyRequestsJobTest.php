<?php

use App\Application\Jobs\PruneIdempotencyRequestsJob;
use App\Infrastructure\Models\IdempotencyRequest;
use App\Infrastructure\Models\Tenant;
use Illuminate\Support\Str;

it('prunes expired requests for every tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    foreach ([$tenantA, $tenantB] as $tenant) {
        app()->instance('current_tenant', $tenant);

        IdempotencyRequest::query()->create([
            'key' => (string) Str::uuid(),
            'payload_hash' => hash('sha256', 'expired'),
            'response_body' => ['content' => '{}'],
            'response_status' => 201,
            'expires_at' => now()->subMinute(),
        ]);

        IdempotencyRequest::query()->create([
            'key' => (string) Str::uuid(),
            'payload_hash' => hash('sha256', 'active'),
            'response_body' => ['content' => '{}'],
            'response_status' => 201,
            'expires_at' => now()->addHour(),
        ]);
    }

    app()->forgetInstance('current_tenant');

    (new PruneIdempotencyRequestsJob)->handle();

    foreach ([$tenantA, $tenantB] as $tenant) {
        app()->instance('current_tenant', $tenant);

        expect(IdempotencyRequest::query()->count())->toBe(1)
            ->and(IdempotencyRequest::query()->firstOrFail()->expires_at->isFuture())->toBeTrue();
    }
});
