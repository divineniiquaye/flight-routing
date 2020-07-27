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
use Flight\Routing\Interfaces\RouteGroupInterface;
use Flight\Routing\Interfaces\RouteInterface;

class RouteGroup implements RouteGroupInterface
{
    /**
     * Route collection for group activities
     *
     * @var RouteCollectionInterface|RouteInterface[]
     */
    private $collection;

    /**
     * Constructor of the class
     *
     * @param RouteCollectionInterface $collection
     */
    public function __construct(RouteCollectionInterface $collection)
    {
        $this->collection = $collection;
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaults(array $defaults): RouteGroupInterface
    {
        foreach ($this->collection as $route) {
            $route->setDefaults($defaults);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addPrefix(string $prefix): RouteGroupInterface
    {
        foreach ($this->collection as $route) {
            $route->addPrefix($prefix);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addDomain(string $domain): RouteGroupInterface
    {
        foreach ($this->collection as $route) {
            $route->setDomain($domain);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addScheme(string ...$schemes): RouteGroupInterface
    {
        foreach ($this->collection as $route) {
            $route->setScheme(...$schemes);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addMethod(string ...$methods): RouteGroupInterface
    {
        foreach ($this->collection as $route) {
            $route->addMethod(...$methods);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addMiddleware(...$middlewares): RouteGroupInterface
    {
        foreach ($this->collection as $route) {
            $route->addMiddleware(...$middlewares);
        }

        return $this;
    }
}
