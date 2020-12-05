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

interface RouteFactoryInterface
{
    /**
     * Creates a route collection with the given route(s)
     *
     * @param RouteInterface ...$routes
     *
     * @return RouteCollectionInterface
     */
    public function createCollection(RouteInterface ...$routes): RouteCollectionInterface;

    /**
     * Creates a new route from the given parameters
     *
     * @param string                                   $name
     * @param string[]                                 $methods
     * @param string                                   $path
     * @param null|array<mixed,string>|callable|string $handler
     *
     * @return RouteInterface
     */
    public function createRoute(string $name, array $methods, string $path, $handler): RouteInterface;
}
