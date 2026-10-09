<?php

declare(strict_types=1);

namespace App\Admin\Services\Wechat;

use App\Admin\Support\ApiException;
use Loongs\Orm\Orm;

/**
 * admin_wechat: one WeChat openid (per mini program AppID) ↔ one admin. Created only by an admin who
 * is already signed in with username + password (POST /admin/api/auth/wechat/bind); the WeChat grant
 * then only logs in openids found here.
 */
final class WechatBindings
{
    public function __construct(
        private readonly Code2SessionClient $client,
    ) {
    }

    public function client(): Code2SessionClient
    {
        return $this->client;
    }

    /** @return array<string, mixed>|null */
    public function forAdmin(int $adminId): ?array
    {
        return Orm::table('admin_wechat')->where('admin_id', $adminId)->where('appid', $this->client->appId())->first();
    }

    /** @return array<string, mixed>|null */
    public function forOpenid(string $openid): ?array
    {
        return Orm::table('admin_wechat')->where('appid', $this->client->appId())->where('openid', $openid)->first();
    }

    /**
     * Bind the WeChat user behind $code to $adminId (replacing the admin's previous binding for this
     * AppID). An openid already bound to another admin → 42202.
     */
    public function bind(int $adminId, string $code): array
    {
        $s = $this->client->exchange($code);
        $appId = $this->client->appId();
        $now = date('Y-m-d H:i:s');

        return Orm::transaction(static function () use ($adminId, $s, $appId, $now): array {
            $other = Orm::table('admin_wechat')->where('appid', $appId)->where('openid', $s->openid)->first();
            if ($other !== null && (int) $other['admin_id'] !== $adminId) {
                throw ApiException::conflict('该微信已绑定其他管理员账号，请先在该账号中解绑');
            }
            if ($other !== null) {
                return ['created' => false, 'bound_at' => $other['created_at']];
            }
            Orm::table('admin_wechat')->where('admin_id', $adminId)->where('appid', $appId)->delete();
            Orm::table('admin_wechat')->insert(['admin_id' => $adminId, 'appid' => $appId, 'openid' => $s->openid, 'unionid' => $s->unionid,
                'created_at' => $now, 'last_login_at' => null]);

            return ['created' => true, 'bound_at' => $now];
        });
    }

    public function unbind(int $adminId): int
    {
        return (int) Orm::table('admin_wechat')->where('admin_id', $adminId)->where('appid', $this->client->appId())->delete();
    }

    public function touch(int $id): void
    {
        Orm::table('admin_wechat')->where('id', $id)->update(['last_login_at' => date('Y-m-d H:i:s')]);
    }
}
