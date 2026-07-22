<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cold Archive Storage
    |--------------------------------------------------------------------------
    |
    | Append-only historical datasets are streamed to this disk as deterministic
    | CSV objects. Canonical database rows are never deleted by archival jobs.
    |
    */

    'disk' => env('ARCHIVE_DISK', 's3'),

    'path_prefix' => env('ARCHIVE_PATH_PREFIX', 'archives'),

    'retention_years' => (int) env('ARCHIVE_RETENTION_YEARS', 5),

    'schema_version' => env('ARCHIVE_SCHEMA_VERSION', '1'),

    'chunk_size' => (int) env('ARCHIVE_CHUNK_SIZE', 500),

    /*
    | Job timeout + grace used to reclaim stale Exporting rows under lockForUpdate.
    */
    'export_timeout_seconds' => (int) env('ARCHIVE_EXPORT_TIMEOUT', 300),

    'export_claim_grace_seconds' => (int) env('ARCHIVE_EXPORT_CLAIM_GRACE', 30),

    /*
    | Upload options set Cache-Control/metadata hints only. True immutability
    | (S3 Object Lock and/or bucket versioning) must be enforced in infrastructure;
    | application metadata alone does not guarantee objects cannot be overwritten.
    */
    'upload' => [
        'visibility' => 'private',
        'options' => [
            'CacheControl' => 'max-age=31536000, immutable',
            'ContentType' => 'text/csv',
            'Metadata' => [
                'immutable' => 'true',
            ],
        ],
        'manifest_options' => [
            'CacheControl' => 'max-age=31536000, immutable',
            'ContentType' => 'application/json',
            'Metadata' => [
                'immutable' => 'true',
            ],
        ],
    ],

];
