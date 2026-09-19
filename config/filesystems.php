<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // Private bucket shared with the HR app (REQ-HRX-10). Never public;
        // files are only ever opened through 10-minute temporary URLs issued
        // by App\Services\Onboarding\OnboardingVault after an ownership check.
        'hr_onboarding' => env('HR_ONBOARDING_DISK_DRIVER', 's3') === 'local'
            ? [
                'driver' => 'local',
                'root' => env('HR_ONBOARDING_LOCAL_ROOT', storage_path('app/hr_onboarding')),
                'serve' => true,
                // Served disks must each have a unique URL; files under it
                // are only reachable with a valid signature (private visibility).
                'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/private-onboarding',
                'visibility' => 'private',
                'throw' => true,
                'report' => false,
            ]
            : [
                'driver' => 's3',
                'key' => env('HR_ONBOARDING_DISK_KEY'),
                'secret' => env('HR_ONBOARDING_DISK_SECRET'),
                'region' => env('HR_ONBOARDING_DISK_REGION'),
                'bucket' => env('HR_ONBOARDING_DISK_BUCKET'),
                'endpoint' => env('HR_ONBOARDING_DISK_ENDPOINT'),
                'use_path_style_endpoint' => env('HR_ONBOARDING_DISK_PATH_STYLE', false),
                'visibility' => 'private',
                'options' => ['ServerSideEncryption' => 'AES256'],
                'throw' => true,
                'report' => false,
            ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
