<?php

declare(strict_types=1);

return [
    'commands' => [
        \App\Admin\Console\InstallCommand::class,   // ./loongs admin:install
        \App\Admin\Console\MenusCommand::class,     // ./loongs admin:menus
    ],
];
