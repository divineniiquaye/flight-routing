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

namespace Flight\Routing;

use Flight\Routing\Interfaces\RouteCollectionInterface;
use Flight\Routing\Interfaces\RouteFactoryInterface;
use Flight\Routing\Interfaces\RouteInterface;
use Flight\Routing\Route;

class RouteFactory implements RouteFactoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function createCollection(RouteInterface ...$routes): RouteCollectionInterface
    {
        return new RouteCollection(...$routes);
    }

    /**
     * {@inheritDoc}
     */
    public function createRoute(string $name, array $methods, string $path, $handler): RouteInterface
    {
        return new Route($name, $methods, $path, $handler);
    }
}
