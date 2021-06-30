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

namespace Flight\Routing\Traits;

use Biurad\Annotations\LoaderInterface;
use Flight\Routing\{DebugRoute, Route};
use Flight\Routing\Interfaces\RouteCompilerInterface;
use Psr\Cache\CacheItemPoolInterface;

trait GroupingTrait
{
    /** @var array */
    private $routesMap = [];

    /** @var Route[]|\SplFixedArray<int,Route> */
    private $routes = [];

    /** @var bool */
    private $hasGroups = false;

    /** @var self|null */
    private $parent = null;

    /** @var array<string,mixed[]>|null */
    private $stack = null;

    /** @var int */
    private $countRoutes = 0;

    /** @var DebugRoute|null */
    private $profiler;

    /** @var RouteCompilerInterface */
    private $compiler;

    /**
     * If routes was debugged, return the profiler.
     */
    public function getDebugRoute(): ?DebugRoute
    {
        return $this->profiler;
    }

    /**
     * Load routes from annotation.
     */
    public function loadAnnotation(LoaderInterface $loader): void
    {
        $annotations = $loader->load();

        foreach ($annotations as $annotation) {
            if ($annotation instanceof self) {
                $this->hasGroups = true;
                $this->routes[] = $annotation;
            }
        }
    }

    /**
     * Bind route with collection.
     */
    private function resolveWith(Route $route): Route
    {
        if (null !== $stack = $this->stack) {
            foreach ($stack as $routeMethod => $arguments) {
                if (empty($arguments)) {
                    continue;
                }

                \call_user_func_array([$route, $routeMethod], 'prefix' === $routeMethod ? [\implode('', $arguments)] : $arguments);
            }
        }

        if (null !== $this->parent) {
            $route->end($this);
        }

        ++$this->countRoutes;

        return $route;
    }

    /**
     * @param \ArrayIterator<int,Route> $routes
     *
     * @return \SplFixedArray<int,Route>
     */
    private function doMerge(string $prefix, \ArrayIterator $routes, bool $merge = true): \SplFixedArray
    {
        $unnamedRoutes = [];

        /** @var Route|RouteCollection $route */
        foreach ($this->routes as $namePrefix => $route) {
            if ($route instanceof self) {
                $route->doMerge($prefix . (\is_string($namePrefix) ? $namePrefix : ''), $routes, false);

                continue;
            }

            $routes['map'][] = $route->bind($name = $prefix . $this->generateRouteName($route, $unnamedRoutes));

            if (isset($routes['profile']) || null !== $this->profiler) {
                $routes['profile'][$name] = new DebugRoute($route);
            }

            $this->processRouteMaps($route, $this->compiler, $routes);
        }

        if ($merge) {
            $this->countRoutes = $routes['countRoutes'] ?? $this->countRoutes;
            $this->routesMap = [$routes['staticRouteMap'] ?? [], 2 => $routes['variableRouteData'] ?? []];

            if (isset($routes['profile'])) {
                $this->profiler->populateProfiler($routes['profile']);
            }

            // Split to prevent a too large regex error ...
            if (isset($routes['dynamicRoutesMap'])) {
                foreach (\array_chunk($routes['dynamicRoutesMap'], 150, true) as $dynamicRoute) {
                    $this->routesMap[1][] = '~^(?|' . \implode('|', $dynamicRoute) . ')$~Ju';
                }
            }

            $this->hasGroups = false;
        }

        return \SplFixedArray::fromArray($routes['map']);
    }

    private function processRouteMaps(Route $route, RouteCompilerInterface $compiler, \ArrayIterator $routes): void
    {
        $methods = \array_unique($route->get('methods'));
        $routeId = $routes['countRoutes'] ?? $routes['countRoutes'] = 0;

        [$pathRegex, $hostsRegex, $variables] = $compiler->compile($route);
        $routes['variableRouteData'][] = $variables;

        if ('\\' === $pathRegex[0]) {
            $hostsRegex = empty($hostsRegex) ? '?(?:\\/{2}[^\/]+)?' : '\\/{2}(?i:(?|' . \implode('|', $hostsRegex) . '))';
            $regex = \preg_replace('/\?(?|P<\w+>|<\w+>|\'\w+\')/', '', $hostsRegex . $pathRegex);

            $routes['dynamicRoutesMap'][] = '(?|' . \implode('|', $methods) . '|([A-Z]+))' . $regex . '(*:' . $routeId . ')';
        } else {
            $routes['staticRouteMap'][$pathRegex] = [$routeId, \array_flip($methods), !empty($hostsRegex) ? '#^(?|' . \implode('|', $hostsRegex) . ')$#i' : null];
        }

        ++$routes['countRoutes'];
    }

    private function generateRouteName(Route $route, array $unnamedRoutes): string
    {
        if (null === $name = $route->get('name')) {
            $name = $route->generateRouteName('');

            if (isset($unnamedRoutes[$name])) {
                $name .= ('_' !== $name[-1] ? '_' : '') . ++$unnamedRoutes[$name];
            } else {
                $unnamedRoutes[$name] = 0;
            }
        }

        return $name;
    }
}
