<?php

declare(strict_types=1);

/*
 * This file is part of Flight Routing.
 *
 * PHP version 7.4 and above required
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 Biurad Group (https://biurad.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flight\Routing\Traits;

use Flight\Routing\{Route, RouteCollection};

/**
 * A trait providing route collection grouping functionality.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
trait GroupingTrait
{
    /** @var self[] */
    private array $groups = [];

    private string $namedPrefix;

    /**
     * Mounts controllers under the given route prefix.
     *
     * @param string                   $name        The route group prefixed name
     * @param callable|RouteCollection $controllers A RouteCollection instance or a callable for defining routes
     *
     * @throws \TypeError        if $controllers not instance of route collection's class
     * @throws \RuntimeException if locked
     *
     * @return $this
     */
    public function group(string $name, $controllers = null)
    {
        if (null === $this->uniqueId) {
            throw new \RuntimeException('Grouping index invalid or out of range, add group before calling the getRoutes() method.');
        }

        if (\is_callable($controllers)) {
            $routes = new static($name);
            $routes->prototypes = $this->prototypes ?? [];
            $controllers($routes);

            return $this->groups[] = $routes;
        }

        return $this->groups[] = $this->injectGroup($name, $controllers ?? new static($name));
    }

    /**
     * Merge a collection into base.
     *
     * @throws \RuntimeException if locked
     */
    public function populate(self $collection, bool $asGroup = false): void
    {
        if ($asGroup) {
            if (null === $this->uniqueId) {
                throw new \RuntimeException('Populating a route collection as group must be done before calling the getRoutes() method.');
            }

            $this->groups[] = $collection;
        } else {
            $routes = $collection->routes;

            if (!empty($collection->groups)) {
                $collection->injectGroups($collection->namedPrefix, $routes);
            }

            foreach ($routes as $route) {
                $this->routes[] = $this->injectRoute($route);
            }

            $collection->routes = $collection->prototypes = [];
        }
    }

    protected function injectGroup(string $prefix, self $controllers): self
    {
        $controllers->prototypes = $this->prototypes ?? [];
        $controllers->parent = $this;

        if (empty($controllers->namedPrefix)) {
            $controllers->namedPrefix = $prefix;
        }

        return $controllers;
    }

    /**
     * @param array<int,Route> $collection
     */
    private function injectGroups(string $prefix, array &$collection): void
    {
        $unnamedRoutes = [];

        foreach ($this->groups as $group) {
            foreach ($group->routes as $route) {
                if (empty($name = $route->getName())) {
                    $name = $route->generateRouteName('');

                    if (isset($unnamedRoutes[$name])) {
                        $name .= ('_' !== $name[-1] ? '_' : '') . ++$unnamedRoutes[$name];
                    } else {
                        $unnamedRoutes[$name] = 0;
                    }
                }

                $collection[] = $route->bind($prefix . $group->namedPrefix . $name);
            }

            if (!empty($group->groups)) {
                $group->injectGroups($prefix . $group->namedPrefix, $collection);
            }
        }

        $this->groups = [];
    }
}
