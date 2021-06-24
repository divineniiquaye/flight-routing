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
use Flight\Routing\{CompiledRoute, DebugRoute, Route};
use Flight\Routing\Interfaces\RouteCompilerInterface;

trait GroupingTrait
{
    /** @var array<string,array<string,mixed>> */
    private $staticRouteMap = [];

    /** @var array<int,array> */
    private $variableRouteData = [];

    /** @var string[] */
    protected $dynamicRoutesMap = [];

    /** @var Route[]|\ArrayIterator<int,Route> */
    private $routes = [];

    /** @var bool */
    private $hasGroups = false;

    /** @var self|null */
    private $parent = null;

    /** @var array<string,mixed[]>|null */
    private $stack = null;

    /** @var DebugRoute|null */
    private $profiler;

    /** @var RouteCompilerInterface */
    private $compiler;

    public function getRouteMaps(): array
    {
        return [$this->staticRouteMap, $this->dynamicRoutesMap, $this->variableRouteData];
    }

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

        return $route;
    }

    /**
     * @return \ArrayIterator<int,Route>
     */
    private function doMerge(string $prefix, int $routeId, \ArrayIterator $routes): \ArrayIterator
    {
        $unnamedRoutes = [];

        /** @var Route|RouteCollection $route */
        foreach ($this->routes as $namePrefix => $route) {
            if ($route instanceof self) {
                $route->doMerge($prefix . (\is_string($namePrefix) ? $namePrefix : ''), $routeId, $routes);

                continue;
            }

            $routes->append($route->bind($name = $prefix . $this->generateRouteName($route, $unnamedRoutes)));

            if (null !== $this->profiler) {
                $this->profiler->addProfile($name, $route);
            }

            $this->processRouteMaps($route, $this->compiler->compile($route), $routeId);

            ++$routeId;
        }

        $this->hasGroups = false;

        return $routes;
    }

    private function processRouteMaps(Route $route, CompiledRoute $compiledRoute, int $routeId): void
    {
        $methods = \array_unique($route->get('methods'));

        $hostsRegex = $compiledRoute->getHostsRegex();
        $this->variableRouteData[$routeId] = $compiledRoute->getVariables();
        
        $pathRegex = $compiledRoute->getPathRegex();

        if (0 === \strpos($pathRegex, '\\/')) {
            $hostsRegex = empty($hostsRegex) ? '?(?:\/{2}[^\/]+)?' : '\/{2}(?i:' . \implode('|', $hostsRegex) . ')';
            $this->dynamicRoutesMap[] = '(?|' . \implode('|', $methods) . '|([A-Z]+))' . $hostsRegex . $pathRegex . '(*MARK:' . $routeId . ')';
        } else {
            foreach ($methods as $method) {
                $this->staticRouteMap[$pathRegex][$method] = [!empty($hostsRegex) ? '#^(?|' . \implode('|', $hostsRegex) . ')$#i' : null, $routeId];
            }
        }
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
