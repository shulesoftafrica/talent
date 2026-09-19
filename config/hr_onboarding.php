<?php

/*
|--------------------------------------------------------------------------
| HR offer + self-onboarding (REQ-HRX-10)
|--------------------------------------------------------------------------
|
| The feature is OFF unless the switch is on AND the private storage is
| usable AND the shared encryption key is valid, so it can never half-run.
| A local disk is accepted only in local/testing environments; production
| needs the private S3-compatible bucket. The bucket credentials and the
| encryption key must be identical to the ones in the HR app
| (shulesoft_newversion), because both apps read the same files and
| decrypt the same values.
|
*/

$driver = strtolower((string) env('HR_ONBOARDING_DISK_DRIVER', 's3'));
$environment = (string) env('APP_ENV', 'production');

$storageReady = match ($driver) {
    's3' => filled(env('HR_ONBOARDING_DISK_KEY'))
        && filled(env('HR_ONBOARDING_DISK_SECRET'))
        && filled(env('HR_ONBOARDING_DISK_REGION'))
        && filled(env('HR_ONBOARDING_DISK_BUCKET'))
        && class_exists(\League\Flysystem\AwsS3V3\AwsS3V3Adapter::class),
    'local' => in_array($environment, ['local', 'testing'], true)
        && filled(env('HR_ONBOARDING_LOCAL_ROOT')),
    default => false,
};

$rawKey = (string) env('HR_ONBOARDING_ENCRYPTION_KEY', '');
$key = str_starts_with($rawKey, 'base64:') ? (base64_decode(substr($rawKey, 7), true) ?: '') : $rawKey;
$keyReady = $key !== '' && \Illuminate\Encryption\Encrypter::supported($key, 'AES-256-CBC');

return [
    'enabled' => filter_var(env('HR_ONBOARDING_ENABLED', false), FILTER_VALIDATE_BOOLEAN) && $storageReady && $keyReady,

    'disk' => 'hr_onboarding',
    'disk_driver' => $driver,
    'encryption_key' => $rawKey,
    'cipher' => 'AES-256-CBC',

    'url_ttl_minutes' => 10,
];
