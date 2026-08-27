<?php

return [
    'directory' => env('BACKUP_DIRECTORY', storage_path('backups')),
    'mysql_binary' => env('BACKUP_MYSQL_BINARY'),
    'mysqldump_binary' => env('BACKUP_MYSQLDUMP_BINARY'),
    'retain_days' => (int) env('BACKUP_RETAIN_DAYS', 180),
    'retain_count' => (int) env('BACKUP_RETAIN_COUNT', 10),

    's3' => [
        'enabled' => (bool) env('BACKUP_AWS_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Excluded tables
    |--------------------------------------------------------------------------
    |
    | Operational tables (queues, cache, sessions, reset tokens) hold no
    | application data worth restoring. Their structure is still dumped so a
    | restore recreates them empty instead of leaving them missing entirely.
    |
    | The three `telescope_*` tables are debug telemetry, not club data: they
    | are the largest tables in the database within days of being switched on
    | (one row per request, plus a tag row per entry), `telescope:prune` throws
    | them away on a schedule anyway, and a `content` column holds the request
    | payloads and query bindings verbatim — member names, emails and IBANs
    | included. Backing them up would inflate every dump with data that is
    | worthless to restore and sensitive to keep.
    |
    | `tracings` is NOT excluded: the login and action history is club data.
    */
    'exclude_tables' => [
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'sessions',
        'password_reset_tokens',
        'telescope_entries',
        'telescope_entries_tags',
        'telescope_monitoring',
    ],
];
