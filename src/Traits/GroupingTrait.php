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
    private ?self $parent = null;

    /** @var self[] */
    private array $groups = [];

    private ?string $namedPrefix;

    /**
     * Mounts controllers under the given route prefix.
     *
     * @param string|null                   $name        The route group prefixed name
     * @param callable|RouteCollection|null $controllers A RouteCollection instance or a callable for defining routes
     *
     * @throws \TypeError        if $controllers not instance of route collection's class
     * @throws \RuntimeException if locked
     *
     * @return $this
     */
    public function group(string $name = null, $controllers = null)
    {
        if (\is_callable($controllers)) {
            $controllers($routes = $this->injectGroup($name, new static($name)));

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
            $this->groups[] = $this->injectGroup($collection->namedPrefix, $collection);
        } else {
            $this->includeRoute($collection); // Incase of missing end method call on route.

            $routes = $collection->routes;
            $collection->routes = $collection->prototypes = [];

            if (!empty($collection->groups)) {
                $collection->injectGroups($collection->namedPrefix ?? '', $routes);
            }

            foreach ($routes as $route) {
                $this->routes[] = $this->injectRoute($route);
            }
        }
    }

    /**
     * @return $this
     */
    protected function injectGroup(?string $prefix, self $controllers)
    {
        if ($this->locked) {
            throw new \RuntimeException('Cannot add a nested routes collection to a frozen routes collection.');
        }

        $this->includeRoute($controllers); // Incase of missing end method call on route.

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
            $group->includeRoute(); // Incase of missing end method call on route.

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
