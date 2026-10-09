<?php

declare(strict_types=1);

namespace App\Admin\Services\Wechat;

use App\Admin\Models\Admin;
use Loongs\OAuth\AuthorizationServer;
use Loongs\OAuth\Entity\Client;
use Loongs\OAuth\Exception\OAuthException;
use Loongs\OAuth\Grant\ExtensionGrantHandler;
use Loongs\OAuth\Grant\ExtensionGrantResult;
use Loongs\OAuth\Http\OAuthRequest;

/**
 * Token endpoint extension grant (loongs/oauth registerGrant):
 *   POST /oauth/token  grant_type=urn:loongs:params:oauth:grant-type:wechat-mini & code=<wx.login code> & client_id=admin-mobile
 * code → code2Session → openid → bound, enabled admin → access + rotating refresh token.
 * An openid that was never bound is refused: the admin must sign in with username + password in
 * the mini program first (which binds it).
 */
final class WechatMiniGrant implements ExtensionGrantHandler
{
    public const TYPE = 'urn:loongs:params:oauth:grant-type:wechat-mini';
    public const NOT_BOUND = '该微信未绑定管理员账号，请先使用账号密码登录，登录后将自动绑定此微信';

    public function __construct(
        private readonly WechatBindings $bindings,
    ) {
    }

    public function handle(OAuthRequest $request, Client $client, AuthorizationServer $server): ExtensionGrantResult
    {
        $code = (string) $request->param('code', 'post');
        if ($code === '') {
            throw OAuthException::invalidRequest('缺少 code（wx.login 返回的登录凭证）');
        }
        try {
            $session = $this->bindings->client()->exchange($code);
        } catch (WechatException $e) {
            throw $e->kind === 'invalid_code' ? OAuthException::invalidGrant($e->getMessage()) : new OAuthException('temporarily_unavailable', $e->getMessage(), 503);
        }
        $row = $this->bindings->forOpenid($session->openid) ?? throw OAuthException::invalidGrant(self::NOT_BOUND);
        $admin = Admin::query()->withoutGlobalScope('data_scope')->find((int) $row['admin_id']);
        if ($admin === null) {
            throw OAuthException::invalidGrant(self::NOT_BOUND);
        }
        if ((int) $admin->status !== 1) {
            throw OAuthException::invalidGrant('账号已停用');
        }
        $this->bindings->touch((int) $row['id']);
        $admin->forceFill(['last_login_at' => date('Y-m-d H:i:s')])->save();

        return new ExtensionGrantResult((string) $admin->id);
    }
}
