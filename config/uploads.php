<?php

declare(strict_types=1);

$env = static function (string $key, mixed $default = null): mixed {
    if (function_exists('env')) {
        return env($key, $default);
    }
    $v = $_ENV[$key] ?? $_SERVER[$key] ?? false;
    return $v === false ? $default : $v;
};

return [
    'default' => (string) $env('UPLOADS_DISK', 'local'),
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => (string) $env('UPLOADS_LOCAL_ROOT', 'runtime/uploads'),
            'visibility' => 'public',
            'public_url' => rtrim((string) $env('UPLOADS_LOCAL_PUBLIC_URL', ''), '/'),
            'internal_url' => rtrim((string) $env('UPLOADS_LOCAL_INTERNAL_URL', ''), '/'),
        ],
        'aliyun' => [
            'driver' => 'aliyun',
            'access_key' => (string) $env('UPLOADS_ALIYUN_ACCESS_KEY', ''),
            'secret_key' => (string) $env('UPLOADS_ALIYUN_SECRET_KEY', ''),
            'bucket' => (string) $env('UPLOADS_ALIYUN_BUCKET', ''),
            'endpoint' => (string) $env('UPLOADS_ALIYUN_ENDPOINT', ''),
            'domain' => rtrim((string) $env('UPLOADS_ALIYUN_DOMAIN', ''), '/'),
            'private' => filter_var($env('UPLOADS_ALIYUN_PRIVATE', false), FILTER_VALIDATE_BOOLEAN),
            'ssl' => filter_var($env('UPLOADS_ALIYUN_SSL', true), FILTER_VALIDATE_BOOLEAN),
            'prefix' => ltrim((string) $env('UPLOADS_ALIYUN_PREFIX', ''), '/'),
        ],
        'tencent' => [
            'driver' => 'tencent',
            'secret_id' => (string) $env('UPLOADS_TENCENT_SECRET_ID', ''),
            'secret_key' => (string) $env('UPLOADS_TENCENT_SECRET_KEY', ''),
            'region' => (string) $env('UPLOADS_TENCENT_REGION', 'ap-guangzhou'),
            'bucket' => (string) $env('UPLOADS_TENCENT_BUCKET', ''),
            'domain' => rtrim((string) $env('UPLOADS_TENCENT_DOMAIN', ''), '/'),
            'private' => filter_var($env('UPLOADS_TENCENT_PRIVATE', false), FILTER_VALIDATE_BOOLEAN),
            'scheme' => (string) $env('UPLOADS_TENCENT_SCHEME', 'https'),
            'prefix' => ltrim((string) $env('UPLOADS_TENCENT_PREFIX', ''), '/'),
        ],
        'qiniu' => [
            'driver' => 'qiniu',
            'access_key' => (string) $env('UPLOADS_QINIU_ACCESS_KEY', ''),
            'secret_key' => (string) $env('UPLOADS_QINIU_SECRET_KEY', ''),
            'bucket' => (string) $env('UPLOADS_QINIU_BUCKET', ''),
            'domain' => rtrim((string) $env('UPLOADS_QINIU_DOMAIN', ''), '/'),
            'private' => filter_var($env('UPLOADS_QINIU_PRIVATE', false), FILTER_VALIDATE_BOOLEAN),
            'region' => (string) $env('UPLOADS_QINIU_REGION', 'z0'),
            'prefix' => ltrim((string) $env('UPLOADS_QINIU_PREFIX', ''), '/'),
        ],
    ],
];
