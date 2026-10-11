<?php

declare(strict_types=1);

namespace App\Uuims\Modules\Auth\Controller;

use App\Uuims\Models\Uuims;
use App\Uuims\Services\LoginGuard;
use App\Uuims\Support\I18n;
use Loongs\Http\Request;
use Loongs\OAuth\Authorization\AuthorizationDecision;
use Loongs\OAuth\Authorization\AuthorizationRequest;
use Loongs\OAuth\AuthorizationServer;
use Loongs\OAuth\Http\OAuthRequest;
use Loongs\OAuth\Http\OAuthResponse;

/**
 * Login step of the authorization endpoint (README §4): server-rendered page with username,
 * password and captcha; posts back to /oauth/authorize with the validated OAuth parameters as hidden
 * fields. First-party public clients → no consent screen; on success the uuims id is approved and the
 * library redirects (303) to the client's redirect_uri with code + state + iss.
 *
 * CSRF / replay: every form carries a one-time nonce (Redis, 5 min) bound to its captcha.
 *
 * JSON mode (uni-app mini program / H5, which cannot follow browser redirects): when the request has
 * `Accept: application/json` and the redirect URI is one of uuims.oauth.json_redirect_uris, the same
 * step answers {login: {login_nonce, captcha (PNG data URI), error}} instead of HTML; on success the
 * route converts the library's redirect into {code, state, iss} (routes.php). Still authorization
 * code + PKCE: the code is only redeemable with the client's code_verifier.
 *
 * Language / theme (README §19): texts and errors in the request locale (LocaleMiddleware:
 * ui_locales / lang / Accept-Language); ui_theme=light|dark forces the page theme (else
 * prefers-color-scheme). Both are carried through the POST as hidden fields.
 */
final class LoginPage
{
    /** Real argon2id hash of a random string: unknown usernames cost the same as wrong passwords. */
    private static ?string $dummyHash = null;

    /** @param list<string> $jsonRedirectUris */
    public function __construct(
        private readonly LoginGuard $guard,
        private readonly array $jsonRedirectUris = [],
    ) {
    }

    /** JSON mode for this request (see class doc). */
    public function wantsJson(AuthorizationRequest $ar, Request $http): bool
    {
        return in_array($ar->redirectUri, $this->jsonRedirectUris, true)
            && str_contains(strtolower((string) $http->header('accept')), 'application/json');
    }

    public function handle(AuthorizationRequest $ar, OAuthRequest $req, AuthorizationServer $as, Request $http): AuthorizationDecision
    {
        $json = $this->wantsJson($ar, $http);   // local: the page object is a per-worker singleton
        $theme = (string) ($http->input('ui_theme') ?? $http->query('ui_theme') ?? '');
        $page = fn (string $error = '', string $username = '', int $status = 200): AuthorizationDecision => $this->page($json, $ar, $error, $username, $status, $theme);
        if ($req->method !== 'POST' || $req->param('login_nonce', 'post') === null) {
            return $page();
        }
        $username = trim((string) $req->param('username', 'post'));
        $password = (string) $req->param('password', 'post');
        $check = $this->guard->consume((string) $req->param('login_nonce', 'post'), (string) $req->param('captcha', 'post'));
        if ($check === 'expired') {
            return $page('页面已过期，请重新输入', $username);
        }
        if ($username === '' || $password === '') {
            return $page('请输入用户名和密码', $username);
        }
        if (($locked = $this->guard->lockedFor($username)) > 0) {
            return $page(I18n::t('登录失败次数过多，请 {minutes} 分钟后再试', ['minutes' => (int) ceil($locked / 60)]), $username, 429);
        }
        if ($check === 'captcha') {
            return $page('验证码错误', $username);
        }

        $uuims = Uuims::query()->withoutGlobalScope('data_scope')->where('username', $username)->first();
        $ok = password_verify($password, $uuims !== null ? (string) $uuims->getAttribute('password') : (self::$dummyHash ??= Uuims::hashPassword(random_bytes(16))));
        if (!$ok || $uuims === null) {
            $left = $this->guard->fail($username);

            return $page($left > 0 ? I18n::t('用户名或密码错误（还可尝试 {left} 次）', ['left' => $left]) : '登录失败次数过多，账号已临时锁定', $username, 401);
        }
        if ((int) $uuims->status !== 1) {
            return $page('账号已停用', $username, 403);
        }
        $this->guard->clear($username);
        if (password_needs_rehash((string) $uuims->getAttribute('password'), PASSWORD_ARGON2ID)) {
            $uuims->forceFill(['password' => Uuims::hashPassword($password)]);
        }
        $uuims->forceFill([
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => (string) ($http->swoole()->server['remote_addr'] ?? ''),
        ])->save();

        return AuthorizationDecision::approve((string) $uuims->id);
    }

    private function page(bool $json, AuthorizationRequest $ar, string $error = '', string $username = '', int $status = 200, string $theme = ''): AuthorizationDecision
    {
        $captcha = $this->guard->issue($json);
        $error = I18n::msg($error);
        $locale = I18n::locale();
        if ($json) {
            return AuthorizationDecision::respond(OAuthResponse::json(['login' => [
                'client' => $ar->client->name,
                'login_nonce' => $captcha['nonce'],
                'captcha' => 'data:image/png;base64,' . base64_encode((string) ($captcha['png'] ?? '')),
                'error' => $error !== '' ? $error : null,
                'username' => $username,
                'locale' => $locale,
            ]], $status));
        }
        $html = self::render([
            'client' => $ar->client->name,
            'params' => $ar->parameters(),
            'nonce' => $captcha['nonce'],
            'captcha' => 'data:image/svg+xml;base64,' . base64_encode($captcha['svg']),
            'error' => $error,
            'username' => $username,
            'locale' => $locale,
            'theme' => in_array($theme, ['light', 'dark'], true) ? $theme : '',
            'languages' => self::languages($ar->parameters(), $theme),
        ]);

        return AuthorizationDecision::respond(new OAuthResponse($status, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'no-store',
            'X-Frame-Options' => 'DENY',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src data:; frame-ancestors 'none'; base-uri 'none'",
            'Referrer-Policy' => 'no-referrer',
            'Content-Language' => $locale,
        ], $html));
    }

    /** Endonyms of the languages with a pack (+ zh-CN); unknown tags show the tag itself. */
    public const LANGUAGE_NAMES = ['zh-CN' => '简体中文', 'zh-TW' => '繁體中文', 'en-US' => 'English', 'en' => 'English', 'ja-JP' => '日本語', 'ko-KR' => '한국어'];

    /**
     * Switcher links: the same authorize request with another ui_locales.
     *
     * @param array<string, mixed> $params
     * @return list<array{locale: string, name: string, href: string}>
     */
    private static function languages(array $params, string $theme): array
    {
        $out = [];
        foreach (I18n::translator()->supported() as $locale) {
            $q = [...$params, 'ui_locales' => $locale];
            if (in_array($theme, ['light', 'dark'], true)) {
                $q['ui_theme'] = $theme;
            }
            $out[] = ['locale' => $locale, 'name' => self::LANGUAGE_NAMES[$locale] ?? $locale, 'href' => '?' . http_build_query($q, '', '&', PHP_QUERY_RFC3986)];
        }

        return $out;
    }

    /** @param array<string, mixed> $v */
    private static function render(array $v): string
    {
        $e = static fn (mixed $s): string => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $t = static fn (string $text, array $params = []): string => I18n::t($text, $params);
        ob_start();
        require __DIR__ . '/../views/login.php';

        return (string) ob_get_clean();
    }
}
