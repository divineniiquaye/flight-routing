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
use Flight\Routing\Route;
use Flight\Routing\Interfaces\RouteCompilerInterface;

trait GroupingTrait
{
    /** @var self|null */
    private $parent = null;

    /** @var array<string,mixed[]>|null */
    private $stack = null;

    /** @var int */
    private $countRoutes = 0;

    /** @var RouteCompilerInterface */
    private $compiler;

    /**
     * Load routes from annotation.
     */
    public function loadAnnotation(LoaderInterface $loader): void
    {
        $annotations = $loader->load();

        foreach ($annotations as $annotation) {
            if ($annotation instanceof self) {
                $this['group'][] = $annotation;
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

        if (null === $this->parent) {
            $this->processRouteMaps($route, $this->countRoutes, $this);
        } else {
            $route->belong($this); // Attach grouping to route.
        }

        ++$this->countRoutes;

        return $route;
    }

    /**
     * @param \ArrayIterator<string,mixed> $routes
     *
     * @return array<int,Route>
     */
    private function doMerge(string $prefix, self $routes): void
    {
        $unnamedRoutes = [];

        foreach ($this['group'] ?? [] as $namePrefix => $group) {
            $namedGroup = \is_string($namePrefix) ? $namePrefix : '';

            foreach ($group['routes'] ?? [] as $route) {
                $routes['routes'][] = $route->bind($this->generateRouteName($route, $prefix . $namedGroup, $unnamedRoutes));
                $this->processRouteMaps($route, $routes->countRoutes, $routes);

                ++$routes->countRoutes;
            }

            if ($group->offsetExists('group')) {
                $group->doMerge($prefix . $namedGroup, $routes);
            }
        }
    }

    /**
     * @param \ArrayIterator|array $routes
     */
    private function processRouteMaps(Route $route, int $routeId, \ArrayIterator $routes): void
    {
        [$pathRegex, $hostsRegex, $variables] = $this->compiler->compile($route);

        if ('\\' === $pathRegex[0]) {
            $routes['dynamicRoutesMap'][0][] = \preg_replace('/\?(?|P<\w+>|<\w+>|\'\w+\')/', '', (empty($hostsRegex) ? '(?:\\/{2}[^\/]+)?' : '\\/{2}(?i:(?|' . \implode('|', $hostsRegex) . '))') . $pathRegex) . '(*:' . $routeId . ')';
            $routes['dynamicRoutesMap'][1][$routeId] = $variables;
        } else {
            $routes['staticRoutesMap'][$pathRegex] = [$routeId, !empty($hostsRegex) ? '#^(?|' . \implode('|', $hostsRegex) . ')$#i' : null, $variables];
        }
    }

    private function generateRouteName(Route $route, string $prefix, array $unnamedRoutes): string
    {
        if (null === $name = $route->get('name')) {
            $name = $route->generateRouteName('');

            if (isset($unnamedRoutes[$name])) {
                $name .= ('_' !== $name[-1] ? '_' : '') . ++$unnamedRoutes[$name];
            } else {
                $unnamedRoutes[$name] = 0;
            }
        }

        return $prefix . $name;
    }
}
