<?php

declare(strict_types=1);

use App\Uuims\Middleware\AdminAuthMiddleware;

/** Page descriptions need a valid admin token; the page permission is checked by PageService. */
return [
    AdminAuthMiddleware::class,
];
