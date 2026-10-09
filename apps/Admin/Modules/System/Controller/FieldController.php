<?php

declare(strict_types=1);

namespace App\Admin\Modules\System\Controller;

use App\Admin\Support\Api;
use Loongs\Http\Request;
use Loongs\Http\Response;
use Loongs\Orm\Orm;

/** Controllable fields (resource_fields) for the role editor. */
final class FieldController
{
    public function index(Request $request): Response
    {
        return Api::ok(Orm::table('resource_fields')->orderBy('resource')->orderBy('id')->get()->all());
    }
}
