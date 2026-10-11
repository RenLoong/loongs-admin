<?php

declare(strict_types=1);

/**
 * UUIMS platform settings (auth + RBAC). Secrets live in .env only.
 */
$csv = static fn (string $key): array => array_values(array_filter(array_map('trim', explode(',', (string) env($key, '')))));
// Browser origins serving the Flutter web build (comma-separated UUIMS_WEB_ORIGINS; intranet
// LAN IP / hostname OK over http). Each origin registers both <origin>/callback.html (dev) and
// <origin>/uuims/web/callback.html (packed) on uuims-pc, and is added to CORS (config/cors.php).
$webOrigins = array_map(static fn (string $o): string => rtrim($o, '/'), $csv('UUIMS_WEB_ORIGINS'));
$webCallbacks = array_values(array_unique(array_merge(
    array_map(static fn (string $o): string => $o . '/callback.html', $webOrigins),
    // Packed Flutter web under ServeAppPublicMiddleware (/uuims/web/).
    array_map(static fn (string $o): string => $o . '/uuims/web/callback.html', $webOrigins),
)));

return [
    'oauth' => [
        // Default public base URL of this server (clients compare it with the `iss` response parameter).
        'issuer' => rtrim((string) env('OAUTH_ISSUER', 'http://127.0.0.1:21000'), '/'),
        // Further origins this server is reached under (intranet: LAN IP, internal hostname, proxy); a
        // request whose origin is listed gets that origin as issuer. "*" = one label/octet, e.g.
        // http://192.168.1.*:21000. Unlisted Host headers fall back to OAUTH_ISSUER (never reflected).
        'allowed_origins' => $csv('OAUTH_ALLOWED_ORIGINS'),
        // Reverse proxies whose X-Forwarded-Proto/Host/Port are believed (IPs or CIDRs, e.g. 10.0.0.0/8).
        'trusted_proxies' => $csv('TRUSTED_PROXIES'),
        'connection' => (string) env('DB_CONNECTION', 'mysql'),
        'scopes' => ['uuims', 'profile'],
        'default_scopes' => ['uuims', 'profile'],
        'access_token_ttl' => (int) env('OAUTH_ACCESS_TOKEN_TTL', 900),        // short-lived access token
        'refresh_token_ttl' => (int) env('OAUTH_REFRESH_TOKEN_TTL', 604800),   // 7 days, rotated on every use
        'auth_code_ttl' => 60,
        // Set once by `./loongs uuims:install`; changing it invalidates every stored token.
        'token_pepper' => (string) env('OAUTH_TOKEN_PEPPER', ''),
        // Public clients (authorization_code + PKCE S256 + refresh_token). Loopback redirect URIs match any port.
        'clients' => [
            'uuims-pc' => [
                'name' => 'UUIMS PC 端（Flutter）',
                'redirect_uris' => array_values(array_unique(array_merge([
                    'http://127.0.0.1/callback',        // desktop: loopback listener on a random port (RFC 8252)
                    'http://127.0.0.1/callback.html',   // web (dev): build/web/callback.html
                ], $webCallbacks, $csv('UUIMS_PC_REDIRECT_URIS')))),
                'scopes' => ['uuims', 'profile'],
            ],
            'uuims-mobile' => [
                'name' => 'UUIMS 移动端（uni-app）',
                'redirect_uris' => array_values(array_unique(array_merge([
                    'http://127.0.0.1/callback.html',   // H5 (dev)
                    'loongs-mp://auth',                 // mini program / H5 JSON login (no browser redirect, see json_redirect_uris)
                ], $csv('UUIMS_MOBILE_REDIRECT_URIS')))),
                'scopes' => ['uuims', 'profile'],
                // WeChat mini program login of an admin whose openid was bound after a password login
                'extension_grants' => ['urn:loongs:params:oauth:grant-type:wechat-mini'],
            ],
        ],
        // Redirect URIs for which /oauth/authorize also answers JSON (Accept: application/json): the
        // mini program cannot follow browser redirects, so the login step returns {code, state, iss}.
        'json_redirect_uris' => ['loongs-mp://auth'],
    ],
    'login' => [
        'max_failures' => (int) env('UUIMS_LOGIN_MAX_FAILURES', 5),   // per username, then locked
        'lock_seconds' => (int) env('UUIMS_LOGIN_LOCK_SECONDS', 900),
        'captcha_ttl' => 300,
    ],
    'permission_cache_ttl' => 600,
    // Rendered page descriptions (GET /uuims/api/pages/{page}); invalidated with the permission cache.
    'page_cache_ttl' => 600,
    'web_origins' => $webOrigins,
    'wechat_mini' => [
        // Mini program credentials (.env only; empty = WeChat login disabled). Bindings are per AppID.
        'appid' => (string) env('WECHAT_MINI_APPID', ''),
        'secret' => (string) env('WECHAT_MINI_SECRET', ''),
        // Offline code2Session for tests / development ("mock:<openid>" codes). Ignored when APP_ENV=production.
        'mock' => filter_var(env('WECHAT_MINI_MOCK', false), FILTER_VALIDATE_BOOLEAN),
        'timeout' => (float) env('WECHAT_MINI_TIMEOUT', 5),
    ],
];
