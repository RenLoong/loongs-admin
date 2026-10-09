<?php

declare(strict_types=1);

namespace App\Admin\Support;

use Loongs\Http\Request;
use Loongs\Http\Response;

/**
 * Serves the packed PC web and H5 builds, and sends the browser to the
 * matching one. Static files under public/web and public/h5 are served by
 * the framework; this only handles the entry paths.
 */
final class ClientEntry
{
    public static function isMobile(?string $clientHint, ?string $userAgent): bool
    {
        $hint = strtolower(trim((string) $clientHint));
        if ($hint === '?1') {
            return true;
        }
        if ($hint === '?0') {
            return false;
        }

        return (bool) preg_match(
            '/Mobile|Android|iPhone|iPod|iPad|webOS|BlackBerry|IEMobile|Opera Mini|HarmonyOS|OpenHarmony/i',
            (string) $userAgent,
        );
    }

    public static function redirectTarget(bool $mobile): string
    {
        return $mobile ? '/admin/h5/' : '/admin/web/';
    }

    public static function redirect(Request $request): Response
    {
        $to = self::redirectTarget(self::isMobile(
            $request->header('sec-ch-ua-mobile'),
            $request->header('user-agent'),
        ));

        return (new Response())->status(302)->header('Location', $to);
    }

    public static function page(string $which): Response
    {
        $dir = $which === 'h5' ? 'h5' : 'web';
        $index = dirname(__DIR__) . '/public/' . $dir . '/index.html';
        if (!is_file($index)) {
            $script = $dir === 'h5' ? 'mobile/pack.ps1' : 'client/pack.ps1';

            return (new Response())->html($dir . ' build is missing. Run admin/' . $script . '.', 404);
        }

        return Response::file($index)->header('Cache-Control', 'no-cache');
    }
}
