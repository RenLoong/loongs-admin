<?php

declare(strict_types=1);

use Loongs\Http\Request;
use Loongs\Http\Response;

/** @var \Loongs\Routing\Router $router */

// P0 smoke route for the Admin app (real modules arrive in P1+).
$router->get('/admin/api/ping', static function (Request $request): Response {
    return (new Response())->json(data: ['pong' => true, 'app' => 'Admin', 'time' => date('c')]);
});
