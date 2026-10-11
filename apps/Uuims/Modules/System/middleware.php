<?php

declare(strict_types=1);

use App\Uuims\Middleware\AdminAuthMiddleware;

/** Every System route needs a valid admin token; button permissions are declared per route. */
return [
    AdminAuthMiddleware::class,
];
