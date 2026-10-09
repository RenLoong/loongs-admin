<?php

declare(strict_types=1);

use App\Admin\Middleware\PermissionMiddleware as P;
use App\Admin\Modules\Dashboard\Controller\OverviewController as O;

/** @var \Loongs\Routing\Router $router */

// 平台概览 (page admin.dashboard.overview): every block loads its own data (RENDER.md §6.1).
$api = '/admin/api/dashboard/overview';
$need = [P::need('dashboard:overview')];
$router->get("$api/stats", [O::class, 'stats'], $need);
$router->get("$api/admin-trend", [O::class, 'adminTrend'], $need);
$router->get("$api/admin-status", [O::class, 'adminStatus'], $need);
$router->get("$api/dept-admins", [O::class, 'deptAdmins'], $need);

// SAMPLE series (tenant module arrives in P2): fixed data from App\Admin\Samples\SampleTenants.
$s = '/admin/api/samples/overview';
$router->get("$s/tenant-opens", [O::class, 'sampleTenantOpens'], $need);
$router->get("$s/health", [O::class, 'sampleHealth'], $need);
$router->get("$s/tenant-profile", [O::class, 'sampleTenantProfile'], $need);
$router->get("$s/tenant-scale", [O::class, 'sampleTenantScale'], $need);
