<?php

declare(strict_types=1);

use Loongs\Http\Kernel;
use Loongs\Middleware\RequestIdMiddleware;
use Loongs\Middleware\ServeAppPublicMiddleware;
use Server\Http\Middleware\CorsMiddleware;

/** @var \Loongs\Container\Container $container */

// Global middleware: the framework hard-codes [RequestId, ServeAppPublic] in Application::boot()
// and has no config hook, so re-set the list here (routes/*.php load after the Kernel is built).
// CORS runs right after RequestId so preflights never reach routing and every response
// (including 404s) carries the CORS headers. Keep in sync if the framework adds global middleware.
$container->make(Kernel::class)->setMiddleware([
    RequestIdMiddleware::class,
    CorsMiddleware::class,
    ServeAppPublicMiddleware::class,
]);
