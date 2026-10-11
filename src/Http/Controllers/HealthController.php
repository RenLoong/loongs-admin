<?php

declare(strict_types=1);

namespace Server\Http\Controllers;

use Loongs\Config\Repository;
use Loongs\Http\Request;
use Loongs\Http\Response;

/**
 * GET /health — server-level infrastructure endpoint (not part of apps/Uuims).
 * Reports app identity plus platform DB (user_platform) and Redis reachability.
 */
final class HealthController
{
    public function __construct(
        private readonly Repository $config,
    ) {
    }

    public function index(Request $request): Response
    {
        $debug = (bool) $this->config->get('app.debug', false);
        $checks = [
            'db' => $this->check(static function (): array {
                $row = db()->select('SELECT DATABASE() AS db, VERSION() AS version')[0] ?? [];

                return ['database' => $row['db'] ?? null, 'version' => $row['version'] ?? null];
            }, $debug),
            'redis' => $this->check(static function (): array {
                $pong = redis()->run(static fn (object $r): mixed => $r->ping());

                return ['ping' => $pong === true ? 'PONG' : $pong];
            }, $debug),
        ];
        $ok = !in_array(false, array_column($checks, 'ok'), true);

        return (new Response())->json(
            data: [
                'status' => $ok ? 'ok' : 'degraded',
                'app' => (string) $this->config->get('app.name', 'loongs'),
                'env' => (string) $this->config->get('app.env', 'local'),
                'php' => PHP_VERSION,
                'swoole' => defined('SWOOLE_VERSION') ? SWOOLE_VERSION : null,
                'checks' => $checks,
                'request_id' => $request->getAttribute('request_id'),
                'time' => date('c'),
            ],
            message: $ok ? 'ok' : 'degraded',
            code: $ok ? 0 : 50001,
            httpStatus: $ok ? 200 : 503,
        );
    }

    /**
     * @param callable(): array<string, mixed> $probe
     * @return array<string, mixed>
     */
    private function check(callable $probe, bool $debug): array
    {
        $start = microtime(true);
        try {
            $result = ['ok' => true] + $probe();
        } catch (\Throwable $e) {
            $result = ['ok' => false, 'error' => $debug ? $e->getMessage() : 'unavailable'];
        }
        $result['ms'] = round((microtime(true) - $start) * 1000, 2);

        return $result;
    }
}
