<?php

declare(strict_types=1);

/*
 * This file is part of Flight Routing.
 *
 * PHP version 7.4 and above required
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 Biurad Group (https://biurad.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flight\Routing\Middlewares;

use Flight\Routing\Routes\{FastRoute as Route, Route as BaseRoute};
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface, UriInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};

/**
 * Resolved route path against request uri paths and match a valid response status
 * code, including resolving sub-directory paths.
 *
 * The response status code is 302 if the permanent parameter is false (default),
 * and 301 if the redirection is permanent on redirection. If keep request method
 * parameter is true, response code 307, and if permanent is true status code is 308.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
final class PathMiddleware implements MiddlewareInterface
{
    public const SUB_FOLDER = __CLASS__ . '::subFolder';

    private bool $permanent;

    private bool $keepRequestMethod;

    /**
     * @param bool $permanent         Whether the redirection is permanent
     * @param bool $keepRequestMethod Whether redirect action should keep HTTP request method
     */
    public function __construct(bool $permanent = false, bool $keepRequestMethod = false)
    {
        $this->permanent = $permanent;
        $this->keepRequestMethod = $keepRequestMethod;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $requestPath = ($requestUri = self::resolveUri($request))->getPath(); // Determine right request uri path.

        $response = $handler->handle($request);
        $route = $request->getAttribute(Route::class);

        if ($route instanceof Route) {
            // Determine the response code should keep HTTP request method ...
            $statusCode = $this->keepRequestMethod ? ($this->permanent ? 308 : 307) : ($this->permanent ? 301 : 302);

            $routeEndTail = BaseRoute::URL_PREFIX_SLASHES[$route->getPath()[-1]] ?? null;
            $requestEndTail = BaseRoute::URL_PREFIX_SLASHES[$requestPath[-1]] ?? null;

            if ($routeEndTail === $requestEndTail) {
                return $response;
            }

            // Resolve request tail end to avoid conflicts and infinite redirection looping ...
            if (null === $requestEndTail && null !== $routeEndTail) {
                $requestPath .= $routeEndTail;
            } elseif (null === $routeEndTail && null !== $requestEndTail) {
                $requestPath = \substr($requestPath, 0, -1);
            }

            // Allow Redirection if exists and avoid static request.
            return $response->withHeader('Location', (string) $requestUri->withPath($requestPath))->withStatus($statusCode);
        }

        return $response;
    }

    public static function resolveUri(ServerRequestInterface &$request): UriInterface
    {
        $requestUri = $request->getUri();
        $pathInfo = $request->getServerParams()['PATH_INFO'] ?? '';

        // Checks if the project is in a sub-directory, expect PATH_INFO in $_SERVER.
        if ('' !== $pathInfo && $pathInfo !== $requestUri->getPath()) {
            $request = $request->withAttribute(self::SUB_FOLDER, \substr($requestUri->getPath(), 0, -(\strlen($pathInfo))));

            return $requestUri->withPath($pathInfo);
        }

        return $requestUri;
    }
}
