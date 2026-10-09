<?php

declare(strict_types=1);

use App\Admin\Middleware\AdminAuthMiddleware;

/** Dashboard data needs a valid admin token; the permission is declared per route. */
return [
    AdminAuthMiddleware::class,
];
