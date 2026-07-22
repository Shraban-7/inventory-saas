<?php

use App\Application\Jobs\ReconcileStockLevelsJob;
use App\Infrastructure\Models\Role;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Tests\Support\SalesContext;

class QueuePriorityProbeJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $name) {}

    public function handle(): void {}
}

afterEach(function (): void {
    app()->forgetInstance('current_tenant');
    Queue::connection('database')->clear('transactions');
    Queue::connection('database')->clear('notifications');
});

it('pops a later transaction before an earlier notification using queue primitives', function () {
    $connection = Queue::connection('database');
    $connection->pushOn('notifications', new QueuePriorityProbeJob('earlier-notification'));
    $connection->pushOn('transactions', new QueuePriorityProbeJob('later-transaction'));

    $next = null;

    foreach (['transactions', 'reports', 'imports', 'notifications'] as $queue) {
        $next = $connection->pop($queue);

        if ($next !== null) {
            break;
        }
    }

    expect($next)->not->toBeNull()
        ->and($next?->getQueue())->toBe('transactions')
        ->and($connection->size('notifications'))->toBe(1);
    $next?->delete();
});

it('configures four redis horizon supervisors and safe queue timeouts', function () {
    $supervisors = config('horizon.defaults');

    expect(array_keys($supervisors))->toBe([
        'supervisor-transactions',
        'supervisor-reports',
        'supervisor-imports',
        'supervisor-notifications',
    ]);

    foreach ([
        'supervisor-transactions' => 'transactions',
        'supervisor-reports' => 'reports',
        'supervisor-imports' => 'imports',
        'supervisor-notifications' => 'notifications',
    ] as $name => $queue) {
        expect($supervisors[$name]['connection'])->toBe('redis')
            ->and($supervisors[$name]['queue'])->toBe([$queue])
            ->and($supervisors[$name]['timeout'])
            ->toBeLessThan(config('queue.connections.redis.retry_after'))
            ->and(config("horizon.waits.redis:{$queue}"))->toBe(60);
    }

    expect($supervisors['supervisor-reports']['timeout'])
        ->toBeGreaterThan(300)
        ->and((new ReconcileStockLevelsJob)->queue)->toBe('transactions');
});

it('allows horizon only for tenant-wide admins', function () {
    $context = SalesContext::create();

    expect(Gate::forUser($context->user)->allows('viewHorizon'))->toBeTrue();

    $admin = Role::query()->where('name', 'Admin')->firstOrFail();
    $context->user->roles()->updateExistingPivot($admin->getKey(), [
        'branch_id' => $context->branch->getKey(),
    ]);
    $context->user->unsetRelation('roles');
    expect(Gate::forUser($context->user)->allows('viewHorizon'))->toBeFalse();

    $context->user->roles()->detach();
    $manager = Role::query()->where('name', 'Manager')->firstOrFail();
    $context->user->roles()->attach($manager->getKey());
    $context->user->unsetRelation('roles');
    expect(Gate::forUser($context->user)->allows('viewHorizon'))->toBeFalse();
});

it('enforces horizon authorization through the HTTP middleware', function () {
    expect(app()->environment())->toBe('testing');

    $manager = SalesContext::create('Manager');
    app()->forgetInstance('current_tenant');
    $this->actingAs($manager->user)
        ->get('/horizon')
        ->assertForbidden();

    $branchAdmin = SalesContext::create();
    $adminRole = Role::query()->where('name', 'Admin')->firstOrFail();
    $branchAdmin->user->roles()->updateExistingPivot($adminRole->getKey(), [
        'branch_id' => $branchAdmin->branch->getKey(),
    ]);
    $branchAdmin->user->unsetRelation('roles');
    app()->forgetInstance('current_tenant');
    $this->actingAs($branchAdmin->user)
        ->get('/horizon')
        ->assertForbidden();

    $tenantAdmin = SalesContext::create();
    app()->forgetInstance('current_tenant');
    $this->actingAs($tenantAdmin->user)
        ->get('/horizon')
        ->assertSuccessful()
        ->assertSee('Horizon');
});

it('denies horizon for non-admins even when the environment is local', function () {
    $previous = $this->app['env'];
    $this->app['env'] = 'local';

    try {
        expect(app()->environment())->toBe('local');

        $manager = SalesContext::create('Manager');
        app()->forgetInstance('current_tenant');
        $this->actingAs($manager->user)
            ->get('/horizon')
            ->assertForbidden();
    } finally {
        $this->app['env'] = $previous;
    }
});
