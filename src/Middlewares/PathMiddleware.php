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

use Flight\Routing\Interfaces\RouteInterface;
use Flight\Routing\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class PathMiddleware implements MiddlewareInterface
{
    /** @var bool */
    private $permanent;

    /**
     * @param bool $permanent
     */
    public function __construct(bool $permanent = true)
    {
        $this->permanent = $permanent;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $route    = $request->getAttribute(Route::class);
        $response = $handler->handle($request);
        $path     = $this->comparePath($route->getPath(), $request->getUri()->getPath());

        // Allow Redirection if exists and avoid static request.
        if ($route instanceof RouteInterface && null !== $path) {
            $response = $response
                ->withAddedHeader('Location', $path)
                ->withStatus($this->determineResponseCode($request));
        }

        return $response;
    }

    /**
     * Check if the user is on the right uri which was matched.
     * If matched returns null, else returns the path the user should be in.
     *
     * @param string $routeUri
     * @param string $requestUri
     *
     * @return null|string
     */
    private function comparePath(string $routeUri, string $requestUri): ?string
    {
        // Resolve Request Uri.
        $newRequestUri = '/' === $requestUri ? '/' : \rtrim($requestUri, '/');
        $newRouteUri   = '/' === $routeUri ? $routeUri : \rtrim($routeUri, '/');

        $paths = [
            'path'      => \substr($requestUri, \strlen($newRequestUri)),
            'route'     => \substr($routeUri, \strlen($newRouteUri)),
        ];

        if (!empty($paths['route']) && $paths['route'] !== $paths['path']) {
            return $newRequestUri . $paths['route'];
        }

        if (empty($paths['route']) && $paths['route'] !== $paths['path']) {
            return $newRequestUri;
        }

        return null;
    }

    /**
     * Determine the response code according with the method and the permanent config.
     *
     * @param ServerRequestInterface $request
     *
     * @return int
     */
    private function determineResponseCode(ServerRequestInterface $request): int
    {
        if (\in_array($request->getMethod(), ['GET', 'HEAD', 'CONNECT', 'TRACE', 'OPTIONS'], true)) {
            return $this->permanent ? 301 : 302;
        }

        return $this->permanent ? 308 : 307;
    }
}
