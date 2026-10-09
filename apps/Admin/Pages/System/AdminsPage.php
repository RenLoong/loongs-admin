<?php

declare(strict_types=1);

namespace App\Admin\Pages\System;

use App\Admin\Enums\AdminStatus;
use Loongs\Render\AsPage;
use Loongs\Render\Builder\Table;
use Loongs\Render\Component\Action;
use Loongs\Render\Component\Column;
use Loongs\Render\Component\Field;
use Loongs\Render\PageContext;
use Loongs\Render\TablePage;

/**
 * 管理员列表. Rendered generically by the Flutter client since R2 (menu.json page admin.system.admins).
 * Data: GET /admin/api/system/admins (data scope + field permissions applied by the API as well).
 */
#[AsPage('admin.system.admins', permission: 'system:admin:list')]
final class AdminsPage extends TablePage
{
    public function build(PageContext $ctx): Table
    {
        return Table::make('管理员')
            ->breadcrumb(['系统管理', '管理员'])
            ->resource('admin')
            ->api('/admin/api/system/admins')
            ->rowKey('id')
            ->search([
                Field::text('keyword', '用户名/昵称'),
                Field::select('status', '状态')->options(AdminStatus::class),
                Field::treeSelect('dept_id', '部门')->optionsApi('/admin/api/system/depts'),
            ])
            ->columns([
                Column::text('id', 'ID')->fixed('left')->width(70),
                Column::text('username', '用户名')->width(140)->mobile(),
                Column::text('nickname', '昵称')->mobile(),
                Column::text('dept_name', '部门'),
                Column::copyable('mobile', '手机号'),
                Column::text('email', '邮箱'),
                Column::text('roles', '角色')->width(160),   // list of {id, name}: the client joins the names
                Column::tag('status', '状态')->enum(AdminStatus::class)->mobile(),
                Column::datetime('last_login_at', '最后登录')->format('yyyy-MM-dd HH:mm'),
                Column::datetime('created_at', '创建时间')->format('yyyy-MM-dd HH:mm'),
            ])
            ->toolbar([
                Action::openForm('新增', 'admin.system.admin-form', [], 'drawer')->can('system:admin:create')->primary()->icon('plus'),
            ])
            ->rowActions([
                Action::openForm('编辑', 'admin.system.admin-form', ['id' => '{id}'], 'drawer')->can('system:admin:update'),
                Action::openForm('重置密码', 'admin.system.admin-password', ['id' => '{id}'])->can('system:admin:reset-password'),
                Action::request('解绑微信', 'DELETE', '/admin/api/system/admins/{id}/wechat')
                    ->confirm('解绑后 {username} 需重新用账号密码登录小程序并绑定，才能再用微信登录。')->can('system:admin:unbind-wechat')->pcOnly()->message('已解绑'),
                Action::request('删除', 'DELETE', '/admin/api/system/admins/{id}')
                    ->confirm('确定删除 {username}？')->can('system:admin:delete')->danger()->message('已删除')
                    ->hiddenWhen('is_super', 'in', [true, 1]),   // the super admin cannot be deleted (API enforces it too)
            ])
            ->pageSizes([20, 50, 100]);
    }
}
