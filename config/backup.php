<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Backup API Key
    |--------------------------------------------------------------------------
    | Used to authenticate external CRON jobs hitting /api/admin/backup/run.
    | Set BACKUP_API_KEY in your .env file. Generate with: openssl rand -hex 32
    */
    'api_key' => env('BACKUP_API_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Retention Days
    |--------------------------------------------------------------------------
    | Number of days to keep backup files before automatic cleanup.
    */
    'retention_days' => env('BACKUP_RETENTION_DAYS', 7),
];
