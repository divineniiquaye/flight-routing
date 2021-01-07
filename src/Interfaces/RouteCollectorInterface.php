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

/**
 * Aggregate routes for the router.
 *
 * This class provides all(*) methods for creating path+HTTP method-based routes and
 * injecting them into the router:
 *
 * - head
 * - get
 * - post
 * - put
 * - patch
 * - delete
 * - options
 * - any
 * - resource
 *
 * A general `addRoute()` method allows specifying multiple request methods and/or
 * arbitrary request methods when creating a path-based route.
 *
 * Internally, the class performs some checks for duplicate routes when
 * attaching via one of the exposed methods, and will raise an exception when a
 * collision occurs.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
interface RouteCollectorInterface
{
    /**
     * Add route group.
     *
     * @param callable|object $callable or an object with __invoke method
     *
     * @return RouteGroupInterface
     */
    public function group($callable): RouteGroupInterface;

    /**
     * Add HEAD route.
     *
     * @param string $name     The route name
     * @param string $pattern  The route URI pattern
     * @param mixed  $callable The route callback routine
     *
     * @return RouteInterface
     */
    public function head(string $name, string $pattern, $callable): RouteInterface;

    /**
     * Add GET route.
     *
     * @param string $name     The route name
     * @param string $pattern  The route URI pattern
     * @param mixed  $callable The route callback routine
     *
     * @return RouteInterface
     */
    public function get(string $name, string $pattern, $callable): RouteInterface;

    /**
     * Add POST route.
     *
     * @param string $name     The route name
     * @param string $pattern  The route URI pattern
     * @param mixed  $callable The route callback routine
     *
     * @return RouteInterface
     */
    public function post(string $name, string $pattern, $callable): RouteInterface;

    /**
     * Add PUT route.
     *
     * @param string $name     The route name
     * @param string $pattern  The route URI pattern
     * @param mixed  $callable The route callback routine
     *
     * @return RouteInterface
     */
    public function put(string $name, string $pattern, $callable): RouteInterface;

    /**
     * Add PATCH route.
     *
     * @param string $name     The route name
     * @param string $pattern  The route URI pattern
     * @param mixed  $callable The route callback routine
     *
     * @return RouteInterface
     */
    public function patch(string $name, string $pattern, $callable): RouteInterface;

    /**
     * Add DELETE route.
     *
     * @param string $name     The route name
     * @param string $pattern  The route URI pattern
     * @param mixed  $callable The route callback routine
     *
     * @return RouteInterface
     */
    public function delete(string $name, string $pattern, $callable): RouteInterface;

    /**
     * Add OPTIONS route.
     *
     * @param string $name     The route name
     * @param string $pattern  The route URI pattern
     * @param mixed  $callable The route callback routine
     *
     * @return RouteInterface
     */
    public function options(string $name, string $pattern, $callable): RouteInterface;

    /**
     * Add route for any HTTP method.
     *
     * @param string $name     The route name
     * @param string $pattern  The route URI pattern
     * @param mixed  $callable The route callback routine
     *
     * @return RouteInterface
     */
    public function any(string $name, string $pattern, $callable): RouteInterface;

    /**
     * This adds a `__restful` to route name and automatically prefix all the methods with HTTP verb.
     *
     * @param string        $name
     * @param string        $pattern
     * @param object|string $resource
     *
     * @return RouteInterface
     */
    public function resource(string $name, string $pattern, $resource): RouteInterface;
}
