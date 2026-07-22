<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Heartbeats
    |--------------------------------------------------------------------------
    |
    | Workers publish throttled heartbeats for each named queue. Readiness
    | requires a fresh heartbeat for every required queue. Telemetry write
    | failures must never crash a worker.
    |
    */

    'queues' => [
        'transactions',
        'reports',
        'imports',
        'notifications',
    ],

    'heartbeat' => [
        'cache_store' => env('HEALTH_HEARTBEAT_CACHE_STORE'),
        'key_prefix' => 'queue:heartbeat:',
        'idle_ttl_seconds' => (int) env('HEALTH_HEARTBEAT_IDLE_TTL', 45),
        'busy_grace_seconds' => (int) env('HEALTH_HEARTBEAT_BUSY_GRACE', 30),
        'default_worker_timeout' => (int) env('HEALTH_HEARTBEAT_WORKER_TIMEOUT', 90),
        'throttle_seconds' => (int) env('HEALTH_HEARTBEAT_THROTTLE', 5),
    ],

    'readiness' => [
        // Primary DB SELECT 1 is bounded by PDO::ATTR_TIMEOUT (5s) in config/database.php.
        'database_pdo_timeout_seconds' => 5,
        'redis_connect_timeout_seconds' => (float) env('REDIS_CONNECT_TIMEOUT', 2),
        'redis_read_timeout_seconds' => (float) env('REDIS_READ_TIMEOUT', 2),
    ],

];
