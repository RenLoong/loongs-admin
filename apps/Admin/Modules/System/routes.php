<?php

declare(strict_types=1);

use App\Admin\Middleware\PermissionMiddleware as P;
use App\Admin\Modules\System\Controller\AdminController;
use App\Admin\Modules\System\Controller\DeptController;
use App\Admin\Modules\System\Controller\FieldController;
use App\Admin\Modules\System\Controller\MenuController;
use App\Admin\Modules\System\Controller\RoleController;
use App\Admin\Modules\Auth\Controller\WechatController;

/** @var \Loongs\Routing\Router $router */

$api = '/admin/api/system';

// Admins (data scope + field permissions apply)
$router->get("$api/admins", [AdminController::class, 'index'], [P::need('system:admin:list')]);
$router->post("$api/admins", [AdminController::class, 'store'], [P::need('system:admin:create')]);
$router->get("$api/admins/{id}", [AdminController::class, 'show'], [P::need('system:admin:list')]);
$router->put("$api/admins/{id}", [AdminController::class, 'update'], [P::need('system:admin:update')]);
$router->delete("$api/admins/{id}", [AdminController::class, 'destroy'], [P::need('system:admin:delete')]);
$router->put("$api/admins/{id}/password", [AdminController::class, 'resetPassword'], [P::need('system:admin:reset-password')]);
$router->delete("$api/admins/{id}/wechat", [WechatController::class, 'unbindAdmin'], [P::need('system:admin:unbind-wechat')]);

// Roles (permission codes, data scope, field permissions)
$router->get("$api/roles", [RoleController::class, 'index'], [P::need('system:role:list')]);
$router->get("$api/roles/options", [RoleController::class, 'options'], [P::need('system:role:list', 'system:admin:create', 'system:admin:update')]);
$router->post("$api/roles", [RoleController::class, 'store'], [P::need('system:role:create')]);
$router->get("$api/roles/{id}", [RoleController::class, 'show'], [P::need('system:role:list')]);
$router->put("$api/roles/{id}", [RoleController::class, 'update'], [P::need('system:role:update')]);
$router->delete("$api/roles/{id}", [RoleController::class, 'destroy'], [P::need('system:role:delete')]);

// Menus / buttons: read-only merged tree of apps/*/menu.json (edited in the files, not here)
$router->get("$api/menus", [MenuController::class, 'index'], [P::need('system:menu:list', 'system:role:list')]);
$router->get("$api/menus/sources", [MenuController::class, 'sources'], [P::need('system:menu:list')]);

// Departments
$router->get("$api/depts", [DeptController::class, 'index'], [P::need('system:dept:list', 'system:admin:list', 'system:role:list')]);
$router->get("$api/depts/{id}", [DeptController::class, 'show'], [P::need('system:dept:list')]);
$router->post("$api/depts", [DeptController::class, 'store'], [P::need('system:dept:create')]);
$router->put("$api/depts/{id}", [DeptController::class, 'update'], [P::need('system:dept:update')]);
$router->delete("$api/depts/{id}", [DeptController::class, 'destroy'], [P::need('system:dept:delete')]);

// Controllable fields (for the role editor)
$router->get("$api/fields", [FieldController::class, 'index'], [P::need('system:role:list')]);
