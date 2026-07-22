<?php

use App\Application\Listeners\RecordQueueHeartbeat;
use App\Application\Services\Health\QueueHeartbeatService;
use App\Infrastructure\Models\Tenant;
use App\Infrastructure\Models\User;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Monolog\Handler\TestHandler;
use Monolog\Level;

afterEach(function (): void {
    app()->forgetInstance('current_tenant');
    Context::flush();
});

function mockRedisPing(bool $ok = true): void
{
    $connection = Mockery::mock();
    if ($ok) {
        $connection->shouldReceive('ping')->andReturn(true);
    } else {
        $connection->shouldReceive('ping')->andThrow(new RuntimeException('redis down'));
    }

    Redis::shouldReceive('connection')->andReturn($connection);
}

function seedQueueHeartbeats(): void
{
    $heartbeats = app(QueueHeartbeatService::class);

    foreach (config('health.queues') as $queue) {
        $heartbeats->touchIdle((string) $queue);
    }
}

it('returns liveness even when database and redis are unavailable', function () {
    $this->getJson('/healthz')
        ->assertOk()
        ->assertJson(['status' => 'ok']);

    $this->getJson('/up')
        ->assertOk()
        ->assertJson(['status' => 'ok']);
});

it('returns readiness success when dependencies and heartbeats are healthy', function () {
    mockRedisPing(true);
    seedQueueHeartbeats();

    $this->getJson('/readyz')
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonStructure(['status', 'components' => [
            'database',
            'redis',
            'queue:transactions',
            'queue:reports',
            'queue:imports',
            'queue:notifications',
        ]]);
});

it('returns 503 when database readiness fails', function () {
    mockRedisPing(true);
    seedQueueHeartbeats();

    DB::partialMock()
        ->shouldReceive('select')
        ->once()
        ->andThrow(new RuntimeException('db down'));

    $response = $this->getJson('/readyz')->assertStatus(503);

    expect($response->json('components.database'))->toBe('fail')
        ->and($response->json())->not->toHaveKey('exception')
        ->and(json_encode($response->json()))->not->toContain('db down');
});

it('returns 503 when redis readiness fails', function () {
    mockRedisPing(false);
    seedQueueHeartbeats();

    $response = $this->getJson('/readyz')->assertStatus(503);

    expect($response->json('components.redis'))->toBe('fail')
        ->and(json_encode($response->json()))->not->toContain('redis down');
});

it('returns 503 when a required worker heartbeat is missing or expired', function () {
    mockRedisPing(true);

    $heartbeats = app(QueueHeartbeatService::class);
    foreach (['transactions', 'reports', 'imports'] as $queue) {
        $heartbeats->touchIdle($queue);
    }

    $response = $this->getJson('/readyz')->assertStatus(503);

    expect($response->json('components.queue:notifications'))->toBe('fail');
});

it('records idle and busy queue heartbeats', function () {
    $service = app(QueueHeartbeatService::class);

    $service->touchIdle('reports');
    expect($service->status('reports'))->toMatchArray(['queue' => 'reports', 'status' => 'idle']);

    $service->touchBusy('reports', 90);
    expect($service->status('reports'))->toMatchArray(['queue' => 'reports', 'status' => 'busy']);
});

it('swallows heartbeat telemetry failures', function () {
    config(['health.heartbeat.cache_store' => 'missing-store']);

    $listener = app(RecordQueueHeartbeat::class);
    $event = new Looping('redis', 'transactions');

    expect(fn () => $listener->handle($event))->not->toThrow(Throwable::class);
});

it('assigns request ids on success and problem details responses', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->getKey()]);

    Route::middleware(['auth', 'tenant'])->get('/api/test/request-id', fn () => response()->json(['ok' => true]));

    $success = $this->actingAs($user)->getJson('/api/test/request-id')->assertOk();
    auth()->logout();
    $error = $this->getJson('/api/test/request-id')->assertUnauthorized();

    expect(Str::isUuid($success->headers->get('X-Request-ID')))->toBeTrue()
        ->and(Str::isUuid($error->headers->get('X-Request-ID')))->toBeTrue();
});

it('accepts valid incoming request ids and replaces invalid ones', function () {
    $valid = (string) Str::uuid();

    $accepted = $this->withHeader('X-Request-ID', $valid)
        ->getJson('/healthz')
        ->assertOk();
    $replaced = $this->withHeader('X-Request-ID', 'not-a-uuid')
        ->getJson('/healthz')
        ->assertOk();

    expect($accepted->headers->get('X-Request-ID'))->toBe($valid)
        ->and($replaced->headers->get('X-Request-ID'))->not->toBe('not-a-uuid')
        ->and(Str::isUuid($replaced->headers->get('X-Request-ID')))->toBeTrue();
});

it('includes observability keys in non-local json logs and clears context between requests', function () {
    $previousEnv = app()->environment();
    app()->detectEnvironment(fn () => 'production');
    config(['logging.default' => 'stderr', 'logging.channels.stderr.level' => 'debug']);
    Log::forgetChannel('stderr');

    $handler = new TestHandler(Level::Debug);
    Log::channel('stderr')->getLogger()->pushHandler($handler);

    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->getKey()]);

    Route::middleware(['auth', 'tenant'])->get('/api/test/log-context', function () {
        Log::channel('stderr')->warning('observability probe');

        return response()->json(['ok' => true]);
    });

    $this->actingAs($user)->getJson('/api/test/log-context')->assertOk();

    $records = array_values(array_filter(
        $handler->getRecords(),
        fn ($record) => ($record->message ?? null) === 'observability probe',
    ));

    expect($records)->not->toBeEmpty();
    $context = $records[0]->context;

    expect($context)->toHaveKeys(['request_id', 'tenant_id', 'user_id'])
        ->and($context['tenant_id'])->toBe($tenant->getKey())
        ->and($context['user_id'])->toBe($user->getKey())
        ->and($context['request_id'])->not->toBeNull();

    expect(Context::get('request_id'))->toBeNull()
        ->and(Context::get('tenant_id'))->toBeNull()
        ->and(Context::get('user_id'))->toBeNull();

    app()->detectEnvironment(fn () => $previousEnv);
    Log::forgetChannel('stderr');
});

it('keeps request id on unhandled exceptions and non-local error logs', function () {
    $previousEnv = app()->environment();
    app()->detectEnvironment(fn () => 'production');
    config([
        'logging.default' => 'stderr',
        'logging.channels.stderr.level' => 'debug',
        'app.debug' => false,
    ]);
    Log::forgetChannel('stderr');

    $handler = new TestHandler(Level::Debug);
    Log::channel('stderr')->getLogger()->pushHandler($handler);

    Route::get('/api/test/unhandled-boom', function (): never {
        throw new RuntimeException('unhandled boom');
    });

    $response = $this->getJson('/api/test/unhandled-boom');
    $requestId = $response->headers->get('X-Request-ID');

    expect($response->status())->toBeGreaterThanOrEqual(500)
        ->and(Str::isUuid((string) $requestId))->toBeTrue();

    $matched = array_values(array_filter(
        $handler->getRecords(),
        fn ($record) => ($record->context['request_id'] ?? null) === $requestId,
    ));

    expect($matched)->not->toBeEmpty()
        ->and(Context::get('request_id'))->toBeNull();

    app()->detectEnvironment(fn () => $previousEnv);
    Log::forgetChannel('stderr');
});

it('clears request id after kernel termination across sequential requests', function () {
    $previousEnv = app()->environment();
    app()->detectEnvironment(fn () => 'production');
    config(['logging.default' => 'stderr', 'logging.channels.stderr.level' => 'debug']);
    Log::forgetChannel('stderr');

    $handler = new TestHandler(Level::Debug);
    Log::channel('stderr')->getLogger()->pushHandler($handler);

    $firstId = (string) Str::uuid();
    $secondId = (string) Str::uuid();

    $first = $this->withHeader('X-Request-ID', $firstId)
        ->getJson('/healthz')
        ->assertOk();

    expect($first->headers->get('X-Request-ID'))->toBe($firstId)
        ->and(Context::get('request_id'))->toBeNull();

    Log::channel('stderr')->warning('out of request after first');

    $orphaned = array_values(array_filter(
        $handler->getRecords(),
        fn ($record) => ($record->message ?? null) === 'out of request after first',
    ));

    expect($orphaned)->not->toBeEmpty()
        ->and($orphaned[0]->context['request_id'] ?? null)->toBeNull();

    $second = $this->withHeader('X-Request-ID', $secondId)
        ->getJson('/healthz')
        ->assertOk();

    expect($second->headers->get('X-Request-ID'))->toBe($secondId)
        ->and(Context::get('request_id'))->toBeNull()
        ->and($secondId)->not->toBe($firstId);

    Log::channel('stderr')->warning('out of request after second');

    $afterSecond = array_values(array_filter(
        $handler->getRecords(),
        fn ($record) => ($record->message ?? null) === 'out of request after second',
    ));

    expect($afterSecond)->not->toBeEmpty()
        ->and($afterSecond[0]->context['request_id'] ?? null)->toBeNull();

    app()->detectEnvironment(fn () => $previousEnv);
    Log::forgetChannel('stderr');
});
