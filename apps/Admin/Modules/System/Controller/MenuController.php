<?php

declare(strict_types=1);

namespace App\Admin\Modules\System\Controller;

use App\Admin\Services\PermissionService;
use App\Admin\Support\Api;
use App\Admin\Support\I18n;
use Loongs\Http\Request;
use Loongs\Http\Response;

/**
 * Read-only merged menu tree (dirs, menus, buttons) of every apps/<App>/menu.json. Menus are edited
 * in those files (reviewed with the code), not in the database; `./loongs admin:menus` validates them.
 */
final class MenuController
{
    public function __construct(
        private readonly PermissionService $permissions,
    ) {
    }

    public function index(Request $request): Response
    {
        $registry = $this->permissions->registry();

        return Api::ok(I18n::tree($registry->tree()), 'ok');
    }

    /** Where the tree comes from (files + content hash), for the read-only menus page. */
    public function sources(Request $request): Response
    {
        $registry = $this->permissions->registry();

        return Api::ok(['files' => $registry->files(), 'hash' => $registry->hash(), 'codes' => count($registry->codes())]);
    }
}
