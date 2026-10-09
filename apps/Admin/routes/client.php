<?php

declare(strict_types=1);

use App\Admin\Support\ClientEntry;
use Loongs\Http\Request;
use Loongs\Http\Response;

/** @var \Loongs\Routing\Router $router */

$send = static fn (Request $request): Response => ClientEntry::redirect($request);

$router->get('/', $send);
$router->get('/admin', $send);

$router->get('/admin/web', static fn (): Response => ClientEntry::page('web'));
$router->get('/admin/h5', static fn (): Response => ClientEntry::page('h5'));