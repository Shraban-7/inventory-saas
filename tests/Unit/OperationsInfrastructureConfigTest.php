<?php

use Illuminate\Support\Facades\File;
use Tests\TestCase;

uses(TestCase::class);

it('configures non-persistent mysql and reporting pdo options with timeouts', function () {
    $mysql = config('database.connections.mysql.options');
    $reporting = config('database.connections.reporting.options');
    $sqlite = config('database.connections.sqlite');

    expect($mysql)->toMatchArray([
        PDO::ATTR_PERSISTENT => false,
        PDO::ATTR_TIMEOUT => 5,
    ])->and($reporting)->toMatchArray([
        PDO::ATTR_PERSISTENT => false,
        PDO::ATTR_TIMEOUT => 5,
    ])->and($sqlite)->not->toHaveKey('options')
        ->and(array_key_exists('read', config('database.connections.mysql')))->toBeFalse()
        ->and(array_key_exists('write', config('database.connections.mysql')))->toBeFalse();
});

it('routes reporting through DB_READ_HOST with write-host fallback semantics', function () {
    $databaseConfig = File::get(base_path('config/database.php'));

    expect(config('database.max_connections'))->toBe(50)
        ->and($databaseConfig)->toContain("env('DB_WRITE_HOST'")
        ->and($databaseConfig)->toContain("env('DB_READ_HOST'")
        ->and($databaseConfig)->toContain("env('DB_MAX_CONNECTIONS'")
        ->and($databaseConfig)->toContain("env('DB_WRITE_HOST', env('DB_HOST'")
        ->and($databaseConfig)->toContain("env('DB_READ_HOST', env('DB_WRITE_HOST'")
        ->and($databaseConfig)->not->toContain("'read' => ['host'")
        ->and($databaseConfig)->not->toContain("'write' => ['host'");
});

it('assumes horizon supervisors use redis and docker supplies linux process extensions', function () {
    $supervisors = config('horizon.defaults');
    $dockerfile = File::get(base_path('Dockerfile'));

    expect($supervisors)->toHaveKeys([
        'supervisor-transactions',
        'supervisor-reports',
        'supervisor-imports',
        'supervisor-notifications',
    ]);

    foreach ($supervisors as $supervisor) {
        expect($supervisor['connection'])->toBe('redis')
            ->and($supervisor['queue'])->toHaveCount(1);
    }

    expect($dockerfile)->toContain('pcntl')
        ->and($dockerfile)->toContain('posix')
        ->and(config('queue.default') === 'sync' || config('queue.connections.redis.driver') === 'redis')->toBeTrue();
});

it('ships compose proxysql and docker runtime assets for pooling', function () {
    $compose = File::get(base_path('compose.yaml'));
    $proxysql = File::get(base_path('docker/proxysql/proxysql.cnf'));
    $entrypoint = File::get(base_path('docker/proxysql/docker-entrypoint.sh'));
    $appEntrypoint = File::get(base_path('docker/app/entrypoint.sh'));
    $replicaCnf = File::get(base_path('docker/mysql/replica.cnf'));
    $replicaBootstrap = File::get(base_path('docker/mysql/replica-bootstrap.sh'));
    $dockerfile = File::get(base_path('Dockerfile'));

    expect($compose)->toContain('proxysql:')
        ->and($compose)->toContain('mysql-writer:')
        ->and($compose)->toContain('mysql-replica:')
        ->and($compose)->toContain('migrate:')
        ->and($compose)->toContain('horizon:')
        ->and($compose)->toContain('scheduler:')
        ->and($compose)->toContain('DB_MAX_CONNECTIONS')
        ->and($compose)->toContain('DB_WRITE_HOST: proxysql')
        ->and($compose)->toContain('DB_READ_HOST: mysql-replica')
        ->and($compose)->toContain('./docker/proxysql')
        ->and($compose)->toContain('command: ["migrate"]')
        ->and($compose)->toContain('condition: service_completed_successfully')
        ->and($proxysql)->toContain('hostgroup=10')
        ->and($proxysql)->toContain('hostgroup=20')
        ->and($proxysql)->toContain('transaction_persistent=1')
        ->and($proxysql)->toContain('max_connections=__DB_MAX_CONNECTIONS__')
        ->and($proxysql)->toContain('default-writer-no-global-read-split')
        ->and($entrypoint)->toContain('DB_MAX_CONNECTIONS')
        ->and($entrypoint)->toContain('max_connections=${DB_MAX_CONNECTIONS}')
        ->and($appEntrypoint)->toContain('migrate)')
        ->and($appEntrypoint)->toContain('php artisan migrate --force')
        ->and(substr_count($appEntrypoint, 'php artisan migrate --force'))->toBe(1)
        ->and($replicaCnf)->not->toContain('read_only=ON')
        ->and($replicaCnf)->not->toContain('super_read_only=ON')
        ->and($replicaBootstrap)->toContain('SET PERSIST read_only = ON')
        ->and($replicaBootstrap)->toContain('SET PERSIST super_read_only = ON')
        ->and($dockerfile)->toContain('pdo_mysql')
        ->and($dockerfile)->toContain('redis')
        ->and(File::exists(base_path('.dockerignore')))->toBeTrue()
        ->and($compose)->toContain('http://127.0.0.1:8080/healthz')
        ->and($compose)->toContain('${MYSQL_WRITER_PUBLISH_HOST:-127.0.0.1}')
        ->and($compose)->toContain('${MYSQL_REPLICA_PUBLISH_HOST:-127.0.0.1}')
        ->and($compose)->toContain('${PROXYSQL_SQL_PUBLISH_HOST:-127.0.0.1}')
        ->and($compose)->toContain('${PROXYSQL_ADMIN_PUBLISH_HOST:-127.0.0.1}')
        ->and($compose)->toContain('${REDIS_PUBLISH_HOST:-127.0.0.1}')
        ->and(File::exists(base_path('tests/load/proxysql-k6.js')))->toBeTrue()
        ->and(File::exists(base_path('scripts/tests/integration/reporting-replica.sh')))->toBeTrue();
});

it('rejects readiness 503 in the k6 load profile and requires two-hundred VUs', function () {
    $k6 = File::get(base_path('tests/load/proxysql-k6.js'));

    expect($k6)->toContain("'/readyz'")
        ->and($k6)->toContain('vus: 200')
        ->and($k6)->toContain('connection_exhaustion_errors')
        ->and($k6)->toContain('readiness_database_failures')
        ->and($k6)->toContain('res.status === 200')
        ->and($k6)->toContain('res.status !== 503')
        ->and($k6)->not->toMatch('/\|\|\s*r\.status\s*===\s*503/')
        ->and($k6)->not->toMatch('/status is 2xx or expected readiness code/')
        ->and($k6)->toContain("components.database === 'fail'")
        ->and($k6)->toContain('count==0');
});

it('starts Horizon and waits for readyz before optional compose load CI', function () {
    $ci = File::get(base_path('.github/workflows/ci.yml'));

    expect($ci)->toContain('compose-load-integration:')
        ->and($ci)->toMatch('/Start compose stack with Horizon/')
        ->and($ci)->toMatch('/docker compose up -d --build[^\n]*\bhorizon\b/')
        ->and($ci)->toContain('Wait for /readyz 200')
        ->and($ci)->toContain('"queue:transactions":"ok"')
        ->and($ci)->toContain('"queue:reports":"ok"')
        ->and($ci)->toContain('"queue:imports":"ok"')
        ->and($ci)->toContain('"queue:notifications":"ok"')
        ->and($ci)->toContain('K6_TARGET_PATH=/readyz')
        ->and($ci)->not->toMatch('/docker compose up -d --build[^\n]*migrate app\n/');
});

it('keeps replica initialization root-only and defers read-only until bootstrap', function () {
    $compose = File::get(base_path('compose.yaml'));

    expect(preg_match(
        '/x-dev-mysql-replica:.*?(?=\nservices:)/s',
        $compose,
        $replicaEnv,
    ))->toBe(1);

    expect($replicaEnv[0])->toContain('MYSQL_ROOT_PASSWORD:')
        ->and($replicaEnv[0])->not->toMatch('/^\s*MYSQL_DATABASE:/m')
        ->and($replicaEnv[0])->not->toMatch('/^\s*MYSQL_USER:/m')
        ->and($replicaEnv[0])->not->toMatch('/^\s*MYSQL_PASSWORD:/m')
        ->and($compose)->toMatch('/mysql-replica:[\s\S]*?<<: \*dev-mysql-replica/')
        ->and($compose)->not->toMatch('/mysql-replica:[\s\S]*?--read-only=ON/')
        ->and($compose)->not->toMatch('/mysql-replica:[\s\S]*?--super-read-only=ON/')
        ->and($compose)->toMatch('/app:[\s\S]*?migrate:\s*\n\s*condition: service_completed_successfully/')
        ->and($compose)->toMatch('/horizon:[\s\S]*?migrate:\s*\n\s*condition: service_completed_successfully/')
        ->and($compose)->toMatch('/scheduler:[\s\S]*?migrate:\s*\n\s*condition: service_completed_successfully/');
});

it('bounds redis connect and read timeouts for readiness probes', function () {
    $default = config('database.redis.default');
    $cache = config('database.redis.cache');
    $envExample = File::get(base_path('.env.example'));

    expect($default['timeout'])->toBe(2.0)
        ->and($default['read_timeout'])->toBe(2.0)
        ->and($cache['timeout'])->toBe(2.0)
        ->and($cache['read_timeout'])->toBe(2.0)
        ->and(config('health.readiness.redis_connect_timeout_seconds'))->toBe(2.0)
        ->and(config('health.readiness.redis_read_timeout_seconds'))->toBe(2.0)
        ->and(config('health.readiness.database_pdo_timeout_seconds'))->toBe(5)
        ->and(config('database.connections.mysql.options')[PDO::ATTR_TIMEOUT])->toBe(5)
        ->and($envExample)->toContain('REDIS_CONNECT_TIMEOUT=2')
        ->and($envExample)->toContain('REDIS_READ_TIMEOUT=2')
        ->and($envExample)->toContain('ARCHIVE_DISK=s3')
        ->and($envExample)->toContain('HEALTH_HEARTBEAT_IDLE_TTL=45')
        ->and($envExample)->toContain('Object Lock');
});
