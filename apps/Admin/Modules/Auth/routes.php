<?php

declare(strict_types=1);

use App\Admin\Middleware\AdminAuthMiddleware;
use App\Admin\Modules\Auth\Controller\LoginPage;
use App\Admin\Modules\Auth\Controller\ProfileController;
use App\Admin\Modules\Auth\Controller\WechatController;
use App\Admin\Support\OAuthServers;
use Loongs\Http\Request;
use Loongs\OAuth\AuthorizationServer;
use Loongs\OAuth\Framework\Bridge;
use Loongs\OAuth\Framework\OAuthRoutes;
use Loongs\OAuth\Http\OAuthResponse;

/** @var \Loongs\Routing\Router $router */
/** @var \Loongs\Container\Container $container */

/** @var OAuthServers $servers per public origin (intranet: LAN IP / internal hostname), see PublicOrigin */
$servers = $container->make(OAuthServers::class);
$resolve = static fn (Request $r): AuthorizationServer => $servers->forRequest($r);
/** @var list<string> $jsonUris */
$jsonUris = array_values((array) $container->make(\Loongs\Config\Repository::class)->get('admin.oauth.json_redirect_uris', []));

// Authorization endpoint with the login step (registered before OAuthRoutes so it wins the route
// match; the handler also gets the framework Request for the client IP / Accept header).
$authorize = static function (Request $r) use ($resolve, $container, $jsonUris) {
    /** @var LoginPage $page */
    $page = $container->make(LoginPage::class);
    $oreq = Bridge::request($r);
    $res = $resolve($r)->authorize($oreq, static fn ($ar, $req, $as) => $page->handle($ar, $req, $as, $r));

    // JSON mode (mini program): turn the final redirect to a JSON redirect URI into a JSON body.
    $redirectUri = (string) ($oreq->param('redirect_uri', $oreq->method === 'POST' ? 'post' : 'query') ?? '');
    $location = (string) $res->header('Location');
    if (in_array($res->status, [302, 303], true) && in_array($redirectUri, $jsonUris, true)
        && str_contains(strtolower((string) $r->header('accept')), 'application/json')
        && str_starts_with($location, $redirectUri . '?')) {
        parse_str((string) parse_url($location, PHP_URL_QUERY), $q);
        $keep = array_intersect_key($q, array_flip(['code', 'state', 'iss', 'error', 'error_description']));
        $res = OAuthResponse::json($keep, isset($keep['error']) ? 400 : 200);
    }

    return Bridge::response($res);
};
$router->get('/oauth/authorize', $authorize);
$router->post('/oauth/authorize', $authorize);

// token (authorization_code + PKCE, refresh_token rotation, WeChat mini program grant), revoke,
// introspect, RFC 8414 metadata — each answered by the server of the request's public origin
OAuthRoutes::register($router, $resolve, static fn () => throw new LogicException('authorize is handled above'), $container->make(AuthorizationServer::class));

$router->get('/admin/api/auth/me', [ProfileController::class, 'me'], [AdminAuthMiddleware::class]);
$router->get('/admin/api/auth/menus', [ProfileController::class, 'menus'], [AdminAuthMiddleware::class]);
$router->put('/admin/api/auth/password', [ProfileController::class, 'password'], [AdminAuthMiddleware::class]);

// WeChat mini program binding of the signed-in admin (bind after a password login; see WechatMiniGrant)
$router->get('/admin/api/auth/wechat', [WechatController::class, 'status'], [AdminAuthMiddleware::class]);
$router->post('/admin/api/auth/wechat/bind', [WechatController::class, 'bind'], [AdminAuthMiddleware::class]);
$router->delete('/admin/api/auth/wechat/bind', [WechatController::class, 'unbind'], [AdminAuthMiddleware::class]);
