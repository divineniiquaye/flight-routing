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

use Fig\Http\Message\RequestMethodInterface;

interface RouteCollectorInterface extends RequestMethodInterface
{
    /**
     * Standard HTTP methods against which to test HEAD/OPTIONS requests.
     */
    public const HTTP_METHODS_STANDARD = [
        self::METHOD_HEAD,
        self::METHOD_GET,
        self::METHOD_POST,
        self::METHOD_PUT,
        self::METHOD_PATCH,
        self::METHOD_DELETE,
        self::METHOD_PURGE,
        self::METHOD_OPTIONS,
        self::METHOD_TRACE,
        self::METHOD_CONNECT,
    ];

    /**
     * Gets the collector collection
     *
     * @return RouteCollectionInterface
     */
    public function getCollection(): RouteCollectionInterface;

    /**
     * Add route group.
     *
     * @param callable|object $callable or an object with __invoke method
     *
     * @return RouteGroupInterface
     */
    public function group($callable): RouteGroupInterface;

    /**
     * Add route.
     *
     * @param string                               $name The route name
     * @param string[]                             $methods Array of HTTP methods
     * @param string                               $pattern The route pattern
     * @param null|callable|object|string|string[] $handler The route callable
     *
     * @return RouteInterface
     */
    public function map(string $name, array $methods, string $pattern, $handler): RouteInterface;

    /**
     * Add HEAD route.
     *
     * @param string                      $name     The route name
     * @param string                      $pattern  The route URI pattern
     * @param null|callable|object|string $callable The route callback routine
     *
     * @return RouteInterface
     */
    public function head(string $name, string $pattern, $callable): RouteInterface;

    /**
     * Add GET route.
     *
     * @param string                      $name     The route name
     * @param string                      $pattern  The route URI pattern
     * @param null|callable|object|string $callable The route callback routine
     *
     * @return RouteInterface
     */
    public function get(string $name, string $pattern, $callable): RouteInterface;

    /**
     * Add POST route.
     *
     * @param string                      $name     The route name
     * @param string                      $pattern  The route URI pattern
     * @param null|callable|object|string $callable The route callback routine
     *
     * @return RouteInterface
     */
    public function post(string $name, string $pattern, $callable): RouteInterface;

    /**
     * Add PUT route.
     *
     * @param string                      $name     The route name
     * @param string                      $pattern  The route URI pattern
     * @param null|callable|object|string $callable The route callback routine
     *
     * @return RouteInterface
     */
    public function put(string $name, string $pattern, $callable): RouteInterface;

    /**
     * Add PATCH route.
     *
     * @param string                      $name     The route name
     * @param string                      $pattern  The route URI pattern
     * @param null|callable|object|string $callable The route callback routine
     *
     * @return RouteInterface
     */
    public function patch(string $name, string $pattern, $callable): RouteInterface;

    /**
     * Add DELETE route.
     *
     * @param string                      $name     The route name
     * @param string                      $pattern  The route URI pattern
     * @param null|callable|object|string $callable The route callback routine
     *
     * @return RouteInterface
     */
    public function delete(string $name, string $pattern, $callable): RouteInterface;

    /**
     * Add OPTIONS route.
     *
     * @param string                      $name     The route name
     * @param string                      $pattern  The route URI pattern
     * @param null|callable|object|string $callable The route callback routine
     *
     * @return RouteInterface
     */
    public function options(string $name, string $pattern, $callable): RouteInterface;

    /**
     * Add route for any HTTP method.
     *
     * @param string                      $name     The route name
     * @param string                      $pattern  The route URI pattern
     * @param null|callable|object|string $callable The route callback routine
     *
     * @return RouteInterface
     */
    public function any(string $name, string $pattern, $callable): RouteInterface;
}
