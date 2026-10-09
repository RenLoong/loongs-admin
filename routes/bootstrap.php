<?php

declare(strict_types=1);

use Loongs\Orm\Orm;

/** @var \Loongs\Http\Application $app */

// loongs/orm reads named connections from config/database.php (framework pools serve them in workers).
Orm::configure(static fn (): array => (array) $app->config()->get('database', []));
