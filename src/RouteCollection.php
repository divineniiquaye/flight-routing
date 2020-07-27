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

use ArrayIterator;
use CachingIterator;
use Flight\Routing\Interfaces\RouteCollectionInterface;
use Flight\Routing\Interfaces\RouteInterface;

/**
 * {@inheritdoc}
 */
class RouteCollection implements RouteCollectionInterface
{
    /**
     * The collection routes
     *
     * @var RouteInterface[]
     */
    private $routes;

    /**
     * Constructor of the class
     *
     * @param RouteInterface ...$routes
     */
    public function __construct(RouteInterface ...$routes)
    {
        $this->routes = $routes;
    }

    /**
     * {@inheritDoc}
     */
    public function add(RouteInterface ...$routes): void
    {
        foreach ($routes as $route) {
            $this->routes[] = $route;
        }
    }

    /**
     * All added routes should be clonable.
     */
    public function __clone()
    {
        foreach ($this->routes as $name => $route) {
            $this->routes[$name] = clone $route;
        }
    }

    /**
     * Gets all routes from the collection
     *
     * {@inheritDoc}
     */
    public function getIterator()
    {
        return new CachingIterator(new ArrayIterator($this->routes), CachingIterator::FULL_CACHE);
    }
}
