<?php

declare(strict_types=1);

use App\Admin\Modules\Render\Controller\PageController;

/** @var \Loongs\Routing\Router $router */

// Server-rendered page descriptions (loongs/render, RENDER.md §2): one endpoint for PC and uni-app.
$router->get('/admin/api/pages/{page}', [PageController::class, 'show']);
