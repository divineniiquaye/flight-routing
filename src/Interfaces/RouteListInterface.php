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
     * Create a new route and add to list
     *
     * @param string $name
     * @param array $methods
     * @param string $pattern
     * @param mixed $handler
     *
     * @return RouteListInterface
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
}
