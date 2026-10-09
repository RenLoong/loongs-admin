<?php

declare(strict_types=1);

/**
 * CORS for browser clients (Flutter web, uni-app H5). Applied globally by
 * Server\Http\Middleware\CorsMiddleware (registered in routes/cors.php).
 *
 * CORS_ALLOWED_ORIGINS: comma-separated exact origins, or * (dev only). ADMIN_WEB_ORIGINS (the origins
 * serving the admin web build, see config/admin.php) are always added.
 */
$list = static fn (string $key, string $default = ''): array => array_values(array_filter(array_map('trim', explode(',', (string) env($key, $default)))));

return [
    'allowed_origins' => array_values(array_unique(array_merge($list('CORS_ALLOWED_ORIGINS', '*'), array_map(static fn (string $o): string => rtrim($o, '/'), $list('ADMIN_WEB_ORIGINS'))))),
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_headers' => ['Authorization', 'Content-Type', 'Accept', 'X-Requested-With', 'X-Request-Id', 'api-version',
        // loongs/render page descriptions (RENDER.md §2.1, §4.5)
        'X-Client-Platform', 'X-Client-Env', 'X-Render-Schema', 'X-Render-Components', 'If-None-Match',
        // languages (README §19)
        'Accept-Language'],
    'exposed_headers' => ['X-Request-Id', 'ETag', 'Content-Language'],
    'max_age' => (int) env('CORS_MAX_AGE', 600),
    'supports_credentials' => filter_var(env('CORS_SUPPORTS_CREDENTIALS', false), FILTER_VALIDATE_BOOLEAN),
];
