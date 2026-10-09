<?php

declare(strict_types=1);

namespace App\Admin\Pages\System;

use App\Admin\Enums\DataScope;
use App\Admin\Enums\EnableStatus;
use Loongs\Render\AsPage;
use Loongs\Render\Builder\Table;
use Loongs\Render\Component\Action;
use Loongs\Render\Component\Column;
use Loongs\Render\Component\Field;
use Loongs\Render\PageContext;
use Loongs\Render\TablePage;

/**
 * 角色列表 (R4, replaces the hand-written roles page). Basic fields are edited in the
 * admin.system.role-form dialog; menu / button permissions, data scope and field permissions in the
 * registered custom page admin.system.role-permissions (component role-permission-editor).
 * Data: GET /admin/api/system/roles (rows carry data_scope + admin_count).
 */
#[AsPage('admin.system.roles', permission: 'system:role:list')]
final class RolesPage extends TablePage
{
    public function build(PageContext $ctx): Table
    {
        return Table::make('角色')
            ->breadcrumb(['系统管理', '角色'])
            ->api('/admin/api/system/roles')
            ->rowKey('id')
            ->search([
                Field::text('keyword', '编码/名称'),
            ])
            ->columns([
                Column::text('id', 'ID')->width(70),
                Column::text('code', '编码')->width(140)->mobile(),
                Column::text('name', '名称')->mobile(),
                Column::tag('data_scope', '数据范围')->enum(DataScope::class),
                Column::text('admin_count', '管理员数')->width(90),
                Column::text('sort', '排序')->width(70),
                Column::tag('status', '状态')->enum(EnableStatus::class)->mobile(),
            ])
            ->toolbar([
                Action::openForm('新增', 'admin.system.role-form', [], 'dialog')->can('system:role:create')->primary()->icon('plus'),
            ])
            ->rowActions([
                Action::openForm('编辑', 'admin.system.role-form', ['id' => '{id}'], 'dialog')->can('system:role:update'),
                Action::openForm('权限', 'admin.system.role-permissions', ['id' => '{id}'], 'dialog')->can('system:role:update')->pcOnly(),
                Action::request('删除', 'DELETE', '/admin/api/system/roles/{id}')
                    ->confirm('确定删除角色「{name}」？已分配的管理员将失去该角色的权限。')->can('system:role:delete')->danger()->message('已删除'),
            ])
            ->pageSizes([20, 50, 100]);
    }
}
