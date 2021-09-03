<?php

declare(strict_types=1);

/*
 * This file is part of Flight Routing.
 *
 * PHP version 7.1 and above required
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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Resolves request uri path, route path, and response status.
 *
 * The response status code is 302 if the permanent parameter is false (default),
 * and 301 if the redirection is permanent on redirection.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
final class PathMiddleware implements MiddlewareInterface
{
    /** @var bool */
    private $permanent;

    /** @var bool */
    private $keepRequestMethod;

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
        $response = $handler->handle($request);

        // Supported prefix for uri paths ...
        $trimmedPath = self::getTrimmedPath($requestPath = ($requestUri = $request->getUri())->getPath());

        // Determine the response code should keep HTTP request method ...
        $statusCode = $this->keepRequestMethod ? ($this->permanent ? 308 : 307) : ($this->permanent ? 301 : 302);

        if ($trimmedPath !== $requestPath) {
            return $response->withHeader('Location', (string) $requestUri->withPath($trimmedPath))->withStatus($statusCode);
        }

        $route = $request->getAttribute(Route::class);

        if ($route instanceof Route) {
            $routeEndTail = BaseRoute::URL_PREFIX_SLASHES[$route->get('path')[-1]] ?? null;
            $requestEndTail = BaseRoute::URL_PREFIX_SLASHES[$trimmedPath[-1]] ?? null;

            if ($routeEndTail === $requestEndTail) {
                return $response;
            }

            // Resolve request tail end to avoid conflicts and infinite redirection looping ...
            if (
                null === $routeEndTail && null !== $requestEndTail ||
                (isset($routeEndTail, $requestEndTail) && $routeEndTail !== $requestEndTail)
            ) {
                $trimmedPath = \substr($trimmedPath, 0, -1);
            } elseif (null !== $routeEndTail && null === $requestEndTail) {
                $trimmedPath .= $routeEndTail;
            }

            // Allow Redirection if exists and avoid static request.
            return $response->withAddedHeader('Location', (string) $requestUri->withPath($trimmedPath))->withStatus($statusCode);
        }

        return $response;
    }

    private static function getTrimmedPath(string $requestPath): string
    {
        return \preg_replace('#(?|\\' . \implode('|\\', BaseRoute::URL_PREFIX_SLASHES) . ')+$#', $requestPath[-1], $requestPath);
    }
}
