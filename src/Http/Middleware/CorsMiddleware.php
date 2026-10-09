<?php

declare(strict_types=1);

namespace Server\Http\Middleware;

use Closure;
use Loongs\Config\Repository;
use Loongs\Http\Request;
use Loongs\Http\Response;
use Loongs\Middleware\MiddlewareInterface;

/**
 * Global CORS for the Flutter web client and uni-app H5 (config/cors.php).
 *
 * - Preflight (OPTIONS + Access-Control-Request-Method) is answered here with 204,
 *   before routing, so no OPTIONS routes are needed.
 * - Allowed origins: exact origins, or "*" (reflects the request Origin).
 */
final class CorsMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Repository $config,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->header('origin');
        $allowed = $origin !== null && $origin !== '' && $this->originAllowed($origin);

        if ($request->method() === 'OPTIONS' && $request->header('access-control-request-method') !== null) {
            $response = (new Response())->status(204)->raw('');
            if ($allowed) {
                $this->applyHeaders($response, (string) $origin);
                $response->header('Access-Control-Allow-Methods', implode(', ', (array) $this->config->get('cors.allowed_methods', [])));
                $requested = $request->header('access-control-request-headers');
                $headers = (array) $this->config->get('cors.allowed_headers', []);
                $response->header(
                    'Access-Control-Allow-Headers',
                    in_array('*', $headers, true) && $requested !== null ? $requested : implode(', ', $headers),
                );
                $response->header('Access-Control-Max-Age', (string) (int) $this->config->get('cors.max_age', 600));
            }

            return $response;
        }

        $response = $next($request);
        if ($allowed) {
            $this->applyHeaders($response, (string) $origin);
            $expose = (array) $this->config->get('cors.exposed_headers', []);
            if ($expose !== []) {
                $response->header('Access-Control-Expose-Headers', implode(', ', $expose));
            }
        }

        return $response;
    }

    private function originAllowed(string $origin): bool
    {
        $list = (array) $this->config->get('cors.allowed_origins', []);

        return in_array('*', $list, true) || in_array($origin, $list, true);
    }

    private function applyHeaders(Response $response, string $origin): void
    {
        $response->header('Access-Control-Allow-Origin', $origin);
        $vary = '';
        foreach ($response->getHeaders() as $name => $value) {
            if (strcasecmp((string) $name, 'Vary') === 0) {
                $vary = (string) $value;
            }
        }
        $response->header('Vary', $vary !== '' && stripos($vary, 'Origin') === false ? $vary . ', Origin' : ($vary !== '' ? $vary : 'Origin'));
        if ((bool) $this->config->get('cors.supports_credentials', false)) {
            $response->header('Access-Control-Allow-Credentials', 'true');
        }
    }
}
