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

namespace Flight\Routing\Interfaces;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

interface RouteListInterface extends RouteCollectorInterface
{
    /**
     * Add route to list
     *
     * @param RouteInterface $route
     *
     * @return RouteListInterface
     */
    public function add(RouteInterface $route): self;

    /**
     * Adds a route collection at the end of the current set by appending all
     * routes of the added collection.
     *
     * @param RouteListInterface $collection
     */
    public function addCollection(RouteListInterface $collection): void;

    /**
     * Create a new route and add to list
     *
     * @param string $name
     * @param array  $methods
     * @param string $pattern
     * @param mixed  $handler
     *
     * @return RouteInterface
     */
    public function addRoute(string $name, array $methods, string $pattern, $handler): RouteInterface;

    /**
     * Add route(s) to list
     *
     * @param RouteInterface ...$routes
     *
     * @return RouteListInterface
     */
    public function addForeach(RouteInterface ...$routes): RouteListInterface;

    /**
     * Get all routes in list
     *
     * @return RouteInterface[]
     */
    public function getRoutes(): array;

    /**
     * Adds the given default keys and values to all routes in the collection
     *
     * @param array<string,mixed> $defaults
     */
    public function withDefaults(array $defaults): void;

    /**
     * Adds the given name to all routes in the collection
     *
     * @param string $name
     */
    public function withName(string $name): void;

    /**
     * Adds the given path prefix to all routes in the collection
     *
     * @param string $prefix
     */
    public function withPrefix(string $prefix): void;

    /**
     * Adds the given path domain to all routes in the collection
     *
     * @param string $domain
     */
    public function withDomain(string $domain): void;

    /**
     * Adds the given domain scheme(s) to all routes in the collection
     *
     * @param string ...$schemes
     */
    public function withScheme(string ...$schemes): void;

    /**
     * Adds the given method(s) to all routes in the collection
     *
     * @param string ...$methods
     */
    public function withMethod(string ...$methods): void;

    /**
     * Adds the given middleware(s) to all routes in the collection
     *
     * @param callable|MiddlewareInterface|RequestHandlerInterface|string ...$middlewares
     */
    public function withMiddleware(...$middlewares): void;
}
