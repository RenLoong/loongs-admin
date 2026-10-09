<?php

declare(strict_types=1);

use App\Admin\Middleware\AdminAuthMiddleware;

/** Messages need a valid admin token; the inbox is per admin (no permission code), sending needs message:send. */
return [
    AdminAuthMiddleware::class,
];
