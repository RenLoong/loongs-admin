<?php

declare(strict_types=1);

namespace App\Admin\Middleware;

use App\Admin\Support\AdminContext;
use App\Admin\Support\Api;
use App\Admin\Support\ApiException;
use Closure;
use Loongs\Config\Repository;
use Loongs\Http\Request;
use Loongs\Http\Response;
use Loongs\Middleware\MiddlewareInterface;
use Loongs\Orm\Exceptions\PoolExhaustedException;

/** App-level: renders ApiException / pool exhaustion / unexpected errors as {code, message, data}. */
final class ApiExceptionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Repository $config,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $next($request);
        } catch (ApiException $e) {
            return Api::fail($e->errorCode, $e->getMessage(), $e->httpStatus, $e->data);
        } catch (PoolExhaustedException $e) {
            return Api::fail(50301, '服务繁忙，请稍后重试', 503);
        } catch (\Throwable $e) {
            error_log('[admin] ' . $e::class . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $debug = (bool) $this->config->get('app.debug', false);

            return Api::fail(50001, $debug ? $e::class . ': ' . $e->getMessage() : '服务器内部错误', 500);
        } finally {
            AdminContext::set(null);
        }
    }
}
