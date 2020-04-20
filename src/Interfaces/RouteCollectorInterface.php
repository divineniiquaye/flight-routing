<?php

declare(strict_types=1);

/*
 * This code is under BSD 3-Clause "New" or "Revised" License.
 *
 * PHP version 7 and above required
 *
 * @category  RoutingManager
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 Biurad Group (https://biurad.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * @link      https://www.biurad.com/projects/routingmanager
 * @since     Version 0.1
 */

namespace Flight\Routing\Interfaces;

use Psr\Http\Message\ResponseInterface;
use Flight\Routing\Exceptions\RouteNotFoundException;
use Flight\Routing\Exceptions\UrlGenerationException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;

interface RouteCollectorInterface
{
    public const TYPE_REQUIREMENT = 1;
    public const TYPE_DEFAULT = 0;

    /**
     * Characters that should not be URL encoded.
     *
     * @var array
     */
    public const DONT_ENCODE = [
        // RFC 3986 explicitly allows those in the query/fragment to reference other URIs unencoded
        '%2F' => '/',
        '%3F' => '?',
        // reserved chars that have no special meaning for HTTP URIs in a query or fragment
        // this excludes esp. "&", "=" and also "+" because PHP would treat it as a space (form-encoded)
        '%40' => '@',
        '%3A' => ':',
        '%21' => '!',
        '%3B' => ';',
        '%2C' => ',',
        '%2A' => '*',
        '%3D' => '=',
        '%2B' => '+',
        '%7C' => '|',
        '%26' => '&',
        '%23' => '#',
        '%25' => '%',
    ];

    /**
     * Get path to FastRoute cache file
     *
     * @return null|string
     */
    public function getCacheFile(): ?string;

    /**
     * Set path to FastRoute cache file
     *
     * @param string $cacheFile
     * @return RouteCollectorInterface
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function setCacheFile(string $cacheFile): RouteCollectorInterface;

    /**
     *
     * @return string
     */
    public function getBasePath(): string;

    /**
     *
     * @param string $basePath
     * @return RouteCollectorInterface
     */
    public function setBasePath(string $basePath): RouteCollectorInterface;

    /**
     * Get route objects
     *
     * @return RouteInterface[]|array
     */
    public function getRoutes(): array;

    /**
     * Generate a URI from the named route.
     *
     * Takes the named route and any parameters, and attempts to generate a
     * URI from it. Additional router-dependent query may be passed.
     *
     * Once there are no missing parameters in the URI we will encode
     * the URI and prepare it for returning to the user. If the URI is supposed to
     * be absolute, we will return it as-is. Otherwise we will remove the URL's root.
     *
     * @param string         $routeName  route name
     * @param string[]|array $parameters key => value option pairs to pass to the
     *                                   router for purposes of generating a URI; takes precedence over options
     *                                   present in route used to generate URI
     * @param array         $queryParams Optional query string parameters
     *
     * @return string of fully qualified URL for named route.
     *
     * @throws UrlGenerationException if the route name is not known
     *                                or a parameter value does not match its regex
     */
    public function generateUri(string $routeName, array $parameters = [], array $queryParams = []): ?string;

    /**
     * Set the root controller namespace.
     *
     * @param string $rootNamespace
     *
     * @return $this
     */
    public function setNamespace(?string $rootNamespace = null): RouteCollectorInterface;

    /**
     * Whether return a permanent redirect.
     */
    public function setPermanentRedirection(bool $permanent = true): RouteCollectorInterface;

    /**
     * Get named route object
     *
     * @param string $name Route name
     *
     * @return RouteInterface
     *
     * @throws \RuntimeException   If named route does not exist
     */
    public function getNamedRoute(string $name): RouteInterface;

    /**
     * Remove named route
     *
     * @param string $name Route name
     * @return RouteCollectorInterface
     *
     * @throws \RuntimeException   If named route does not exist
     */
    public function removeNamedRoute(string $name): RouteCollectorInterface;

    /**
     * Lookup a route via the route's unique identifier
     *
     * @param string $identifier
     *
     * @return RouteInterface
     *
     * @throws \RuntimeException   If route of identifier does not exist
     */
    public function addLookupRoute(RouteInterface $route): void;

    /**
     * Get the current route.
     *
     * @return RouteInterface|null
     */
    public function currentRoute(): ?RouteInterface;

    /**
     * Get current http request instance.
     *
     * @return ServerRequestInterface
     */
    public function getRequest(): ServerRequestInterface;

    /**
     * Set the global the middlewares stack attached to all routes.
     *
     * @param array|string|callable|MiddlewareInterface $middleware
     *
     * @return $this|array
     */
    public function addMiddlewares($middleware = []): RouteCollectorInterface;

    /**
     * Set the route middleware and call it as a method on route.
     *
     * @param array $middlewares [name => $middlewares ?? [$middlewares]]
     *
     * @return $this|array
     */
    public function routeMiddlewares($middlewares = []): RouteCollectorInterface;

    /**
     * Adds parameters.
     *
     * This method implements a fluent interface.
     *
     * @param array $parameters The parameters
     *
     * @return $this
     */
    public function addParameters(array $parameters, int $type = self::TYPE_REQUIREMENT): RouteCollectorInterface;

    /**
     * Add route group
     *
     * @param array           $attributes
     * @param string|callable $callable
     */
    public function group(array $attributes = [], $callable): RouteGroupInterface;

    /**
     * Set the controller as Api Resource Controller.
     *
     * Router knows how to respond to resource controller
     * request automatically
     *
     * @param string                  $uri
     * @param Closure|callable|string $controller
     * @param array                   $options
     */
    public function resource($name, $controller, array $options = []);

    /**
     * Add route
     *
     * @param string[]        $methods Array of HTTP methods
     * @param string          $pattern The route pattern
     * @param callable|string $handler The route callable
     *
     * @return RouteInterface
     */
    public function map(array $methods, string $pattern, $handler = null): RouteInterface;

    /**
     * Dispatch routes and run the application.
     *
     * @throws RouteNotFoundException
     * @throws ExceptionInterface
     */
    public function dispatch(): ResponseInterface;
}
