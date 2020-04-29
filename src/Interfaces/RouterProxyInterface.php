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

interface RouterProxyInterface
{
    /**
     * @return RouteCollectorInterface
     */
    public function getRouteCollector(): RouteCollectorInterface;

    /**
     * Get the RouterProxy's base path
     *
     * @return string
     */
    public function getBasePath(): string;

    /**
     * Add GET route
     *
     * @param  string          $pattern  The route URI pattern
     * @param  callable|string $callable The route callback routine
     *
     * @return RouteInterface
     */
    public function get(string $pattern, $callable): RouteInterface;

    /**
     * Add POST route
     *
     * @param  string          $pattern  The route URI pattern
     * @param  callable|string $callable The route callback routine
     *
     * @return RouteInterface
     */
    public function post(string $pattern, $callable): RouteInterface;

    /**
     * Add PUT route
     *
     * @param  string          $pattern  The route URI pattern
     * @param  callable|string $callable The route callback routine
     *
     * @return RouteInterface
     */
    public function put(string $pattern, $callable): RouteInterface;

    /**
     * Add PATCH route
     *
     * @param  string          $pattern  The route URI pattern
     * @param  callable|string $callable The route callback routine
     *
     * @return RouteInterface
     */
    public function patch(string $pattern, $callable): RouteInterface;

    /**
     * Add DELETE route
     *
     * @param  string          $pattern  The route URI pattern
     * @param  callable|string $callable The route callback routine
     *
     * @return RouteInterface
     */
    public function delete(string $pattern, $callable): RouteInterface;

    /**
     * Add OPTIONS route
     *
     * @param  string          $pattern  The route URI pattern
     * @param  callable|string $callable The route callback routine
     *
     * @return RouteInterface
     */
    public function options(string $pattern, $callable): RouteInterface;

    /**
     * Add route for any HTTP method
     *
     * @param  string          $pattern  The route URI pattern
     * @param  callable|string $callable The route callback routine
     *
     * @return RouteInterface
     */
    public function any(string $pattern, $callable): RouteInterface;

    /**
     * Add route with multiple methods
     *
     * @param  string[]        $methods  Numeric array of HTTP method names
     * @param  string          $pattern  The route URI pattern
     * @param  callable|string $callable The route callback routine
     *
     * @return RouteInterface
     */
    public function map(array $methods, string $pattern, $callable): RouteInterface;

    /**
     * Route Groups
     *
     * This method accepts a route pattern and a callback. All route
     * declarations in the callback will be prepended by the group(s)
     * that it is in.
     *
     * @param array           $attributes
     * @param string|callable $callable
     *
     * @return RouteGroupInterface
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
     *
     * @return mixed
     */
    public function resource($name, $controller, array $options = []);
}
