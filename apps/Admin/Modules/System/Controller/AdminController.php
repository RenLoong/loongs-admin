<?php

declare(strict_types=1);

namespace App\Admin\Modules\System\Controller;

use App\Admin\Models\Admin;
use App\Admin\Models\Dept;
use App\Admin\Services\PermissionService;
use App\Admin\Services\TokenService;
use App\Admin\Support\AdminContext;
use App\Admin\Support\Api;
use App\Admin\Support\ApiException;
use App\Admin\Support\Validator;
use Loongs\Http\Request;
use Loongs\Http\Response;
use Loongs\Orm\Orm;

/**
 * Admin CRUD. Row-level data scope comes from the Admin model's global scope (out-of-scope rows are
 * invisible → 404); field permissions from HasFieldPermissions (toArray / fill).
 */
final class AdminController
{
    private const LABELS = ['username' => '用户名', 'password' => '密码', 'nickname' => '昵称', 'mobile' => '手机号',
        'email' => '邮箱', 'dept_id' => '部门', 'status' => '状态', 'role_ids' => '角色'];

    public function __construct(
        private readonly PermissionService $permissions,
    ) {
    }

    public function index(Request $request): Response
    {
        [$page, $size] = Api::pagination($request);
        $q = Admin::query();
        $kw = trim((string) $request->query('keyword', ''));
        if ($kw !== '') {
            $q->where(static fn ($w) => $w->where('username', 'like', "%{$kw}%")->orWhere('nickname', 'like', "%{$kw}%"));
        }
        if (($dept = (int) $request->query('dept_id', 0)) > 0) {
            $q->where('dept_id', $dept);
        }
        $status = $request->query('status');
        if ($status !== null && $status !== '') {
            $q->where('status', (int) $status);
        }
        $p = $q->orderBy('id')->paginate($size, $page);
        $ids = [];
        $deptIds = [];
        foreach ($p->items() as $a) {
            $ids[] = (int) $a->id;
            if ($a->dept_id) {
                $deptIds[] = (int) $a->dept_id;
            }
        }
        $roles = $this->rolesOf($ids);
        $depts = $deptIds === [] ? [] : Orm::table('depts')->whereIn('id', array_unique($deptIds))->pluck('name', 'id')->all();

        return Api::page($p, static fn (Admin $a): array => $a->toArray() + [
            'dept_name' => $depts[(int) $a->dept_id] ?? null,
            'roles' => $roles[(int) $a->id] ?? [],
        ], ['fields' => $this->fieldPerms()]);
    }

    public function show(Request $request): Response
    {
        $admin = $this->find(Api::id($request));

        return Api::ok($admin->toArray() + [
            'roles' => $this->rolesOf([(int) $admin->id])[(int) $admin->id] ?? [],
            'role_ids' => array_map(static fn (array $r): int => $r['id'], $this->rolesOf([(int) $admin->id])[(int) $admin->id] ?? []),
            'fields' => $this->fieldPerms(),
        ]);
    }

    public function store(Request $request): Response
    {
        $input = Api::input($request);
        $in = Validator::check($input, [
            'username' => 'required|string|regex:/^[A-Za-z0-9_]{3,32}$/',
            'password' => 'required|string|min:8|max:72',
            'nickname' => 'string|max:64',
            'mobile' => 'nullable|string|max:32',
            'email' => 'nullable|email|max:128',
            'dept_id' => 'nullable|int',
            'status' => 'int|in:0,1',
            'role_ids' => 'array',
        ], self::LABELS);
        $this->assertDept($in['dept_id'] ?? null);
        if (Admin::query()->withoutGlobalScope('data_scope')->where('username', $in['username'])->exists()) {
            throw ApiException::conflict('用户名已存在');
        }
        $roleIds = Validator::ids($in['role_ids'] ?? []);
        $this->assertAssignable($roleIds);

        $admin = Orm::transaction(function () use ($in, $input, $roleIds): Admin {
            $admin = new Admin();
            // fill() applies field permissions to the raw client input (non-editable fields → 403)
            $admin->fill(array_intersect_key($input, array_flip(['mobile', 'email'])) + [
                'username' => $in['username'],
                'nickname' => $in['nickname'] ?? $in['username'],
                'dept_id' => $in['dept_id'] ?? null,
                'status' => $in['status'] ?? 1,
            ]);
            $admin->forceFill([
                'password' => Admin::hashPassword($in['password']),
                'password_changed_at' => date('Y-m-d H:i:s'),
                'is_super' => 0,
                'created_by' => AdminContext::id(),
            ])->save();
            $this->syncRoles((int) $admin->id, $roleIds);

            return $admin;
        });

        return Api::ok(['id' => (int) $admin->id], '创建成功');
    }

    public function update(Request $request): Response
    {
        $admin = $this->find(Api::id($request));
        $this->assertNotProtected($admin);
        $input = Api::input($request);
        $in = Validator::check($input, [
            'nickname' => 'string|max:64',
            'mobile' => 'nullable|string|max:32',
            'email' => 'nullable|email|max:128',
            'dept_id' => 'nullable|int',
            'status' => 'int|in:0,1',
            'role_ids' => 'array',
        ], self::LABELS);
        if (array_key_exists('dept_id', $in) && (int) $in['dept_id'] !== (int) $admin->dept_id) {
            $this->assertDept($in['dept_id']);
        }
        if ((int) $admin->id === AdminContext::id() && isset($in['status']) && (int) $in['status'] === 0) {
            throw ApiException::conflict('不能停用自己');
        }
        $roleIds = array_key_exists('role_ids', $in) ? Validator::ids($in['role_ids']) : null;
        if ($roleIds !== null) {
            $this->assertAssignable($roleIds, (int) $admin->id);
        }
        $disabled = isset($in['status']) && (int) $in['status'] === 0 && (int) $admin->status === 1;

        Orm::transaction(function () use ($admin, $in, $input, $roleIds): void {
            $attrs = array_intersect_key($in, array_flip(['nickname', 'dept_id', 'status']));
            foreach (['mobile', 'email'] as $f) {
                if (array_key_exists($f, $input)) {
                    $attrs[$f] = $input[$f];
                }
            }
            $admin->fill($attrs)->save();
            if ($roleIds !== null) {
                $this->syncRoles((int) $admin->id, $roleIds);
            }
        });
        $this->permissions->flush();
        if ($disabled) {
            TokenService::revokeAll((int) $admin->id);
        }

        return Api::ok(['id' => (int) $admin->id], '保存成功');
    }

    public function destroy(Request $request): Response
    {
        $admin = $this->find(Api::id($request));
        if ($admin->is_super) {
            throw ApiException::forbidden('超级管理员不能删除', 40305);
        }
        if ((int) $admin->id === AdminContext::id()) {
            throw ApiException::conflict('不能删除自己');
        }
        Orm::transaction(static function () use ($admin): void {
            Orm::table('admin_role')->where('admin_id', $admin->id)->delete();
            Orm::table('admin_wechat')->where('admin_id', $admin->id)->delete();
            Orm::table('admin_messages')->where('admin_id', $admin->id)->delete();   // P5 inbox (table from admin:install)
            $admin->delete();
        });
        TokenService::revokeAll((int) $admin->id);
        $this->permissions->flush();

        return Api::ok(null, '删除成功');
    }

    public function resetPassword(Request $request): Response
    {
        $admin = $this->find(Api::id($request));
        $this->assertNotProtected($admin);
        $in = Validator::check(Api::input($request), ['password' => 'required|string|min:8|max:72'], self::LABELS);
        $admin->forceFill(['password' => Admin::hashPassword($in['password']), 'password_changed_at' => date('Y-m-d H:i:s')])->save();
        $revoked = TokenService::revokeAll((int) $admin->id);

        return Api::ok(['revoked_tokens' => $revoked], '密码已重置');
    }

    private function find(int $id): Admin
    {
        return Admin::query()->find($id) ?? throw ApiException::notFound();
    }

    private function assertNotProtected(Admin $admin): void
    {
        if ($admin->is_super && !AdminContext::isSuper()) {
            throw ApiException::forbidden('不能修改超级管理员', 40305);
        }
    }

    private function assertDept(?int $deptId): void
    {
        if ($deptId !== null && Dept::query()->find($deptId) === null) {
            throw ApiException::validation(['dept_id' => '部门不存在']);
        }
        if (!Admin::deptInScope($deptId)) {
            throw ApiException::forbidden('该部门不在你的数据权限范围内', 40303);
        }
    }

    /** Non-super admins may only assign roles they hold themselves (no privilege escalation). @param list<int> $roleIds */
    private function assertAssignable(array $roleIds, ?int $targetId = null): void
    {
        if ($roleIds === []) {
            return;
        }
        $found = array_map('intval', Orm::table('roles')->whereIn('id', $roleIds)->pluck('id')->all());
        if (count($found) !== count($roleIds)) {
            throw ApiException::validation(['role_ids' => '角色不存在']);
        }
        if (!AdminContext::isSuper()) {
            $current = $targetId === null ? [] : array_map('intval', Orm::table('admin_role')->where('admin_id', $targetId)->pluck('role_id')->all());
            $new = array_diff($roleIds, $current);
            if (array_diff($new, AdminContext::require()['role_ids']) !== []) {
                throw ApiException::forbidden('只能分配自己拥有的角色', 40304);
            }
        }
    }

    /** @param list<int> $roleIds */
    private function syncRoles(int $adminId, array $roleIds): void
    {
        Orm::table('admin_role')->where('admin_id', $adminId)->delete();
        if ($roleIds !== []) {
            Orm::table('admin_role')->insert(array_map(static fn (int $r): array => ['admin_id' => $adminId, 'role_id' => $r], $roleIds));
        }
    }

    /**
     * @param list<int> $adminIds
     * @return array<int, list<array{id: int, name: string}>>
     */
    private function rolesOf(array $adminIds): array
    {
        if ($adminIds === []) {
            return [];
        }
        $out = [];
        $rows = Orm::table('admin_role')->join('roles', 'roles.id', '=', 'admin_role.role_id')
            ->whereIn('admin_role.admin_id', $adminIds)->select('admin_role.admin_id', 'roles.id', 'roles.name')->get()->all();
        foreach ($rows as $r) {
            $out[(int) $r['admin_id']][] = ['id' => (int) $r['id'], 'name' => (string) $r['name']];
        }

        return $out;
    }

    /** @return array<string, string> field => perm for the "admin" resource */
    private function fieldPerms(): array
    {
        return AdminContext::require()['fields']['admin'] ?? [];
    }
}
