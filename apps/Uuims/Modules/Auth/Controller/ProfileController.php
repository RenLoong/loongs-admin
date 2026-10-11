<?php

declare(strict_types=1);

namespace App\Uuims\Modules\Auth\Controller;

use App\Uuims\Models\Admin;
use App\Uuims\Models\Dept;
use App\Uuims\Services\PermissionService;
use App\Uuims\Services\TokenService;
use App\Uuims\Support\AdminContext;
use App\Uuims\Support\Api;
use App\Uuims\Support\ApiException;
use App\Uuims\Support\I18n;
use App\Uuims\Support\Validator;
use Loongs\Http\Request;
use Loongs\Http\Response;

/** Current admin: profile + permission codes, menu tree, own password. */
final class ProfileController
{
    public function __construct(
        private readonly PermissionService $permissions,
    ) {
    }

    public function me(Request $request): Response
    {
        $ctx = AdminContext::require();
        $admin = Admin::query()->withoutGlobalScope('data_scope')->findOrFail($ctx['id']);
        $dept = $admin->dept_id ? Dept::query()->find((int) $admin->dept_id) : null;

        return Api::ok([
            'id' => (int) $admin->id,
            'username' => $admin->username,
            'nickname' => $admin->nickname,
            'mobile' => $admin->mobile,
            'email' => $admin->email,
            'dept' => $dept === null ? null : ['id' => (int) $dept->id, 'name' => $dept->name],
            'is_super' => $ctx['is_super'],
            'roles' => $ctx['roles'],
            'permissions' => $ctx['is_super'] ? ['*'] : $ctx['perms'],
            'data_scope' => $ctx['data'],
            'fields' => $ctx['is_super'] ? new \stdClass() : $ctx['fields'],
            'last_login_at' => $admin->last_login_at,
        ]);
    }

    public function menus(Request $request): Response
    {
        return Api::ok(I18n::tree($this->permissions->menuTree(AdminContext::require())));   // names in the request locale
    }

    /** Change own password → every token of this admin is revoked (log in again). */
    public function password(Request $request): Response
    {
        $in = Validator::check(Api::input($request), ['old_password' => 'required|string', 'new_password' => 'required|string|min:8|max:72'],
            ['old_password' => '原密码', 'new_password' => '新密码']);
        $admin = Admin::query()->withoutGlobalScope('data_scope')->findOrFail(AdminContext::require()['id']);
        if (!password_verify($in['old_password'], (string) $admin->getAttribute('password'))) {
            throw new ApiException(42203, '原密码错误', 422);
        }
        $admin->forceFill(['password' => Admin::hashPassword($in['new_password']), 'password_changed_at' => date('Y-m-d H:i:s')])->save();
        $revoked = TokenService::revokeAll((int) $admin->id);

        return Api::ok(['revoked_tokens' => $revoked], '密码已修改，请重新登录');
    }
}
