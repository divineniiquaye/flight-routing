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

namespace Flight\Routing\Tests\Fixtures;

use Flight\Routing\Route;

/**
 * Helper.
 */
class Helper
{
    /**
     * @param iterable<int,Route> $routes
     *
     * @return array<int,array<string,mixed>>|array<string,mixed>
     */
    public static function routesToArray(iterable $routes, bool $first = false): array
    {
        $result = [];

        foreach ($routes as $route) {
            if (\is_object($controller = $route->getController())) {
                $controller = \get_class($controller);
            }

            $defaults = $route->getDefaults();

            $item = [];
            $item['name'] = $route->getName();
            $item['path'] = $route->getPath();
            $item['domain'] = $route->getDomain();
            $item['methods'] = \array_keys($route->getMethods());
            $item['handler'] = $controller;
            $item['middlewares'] = [];
            $item['schemes'] = \array_keys($route->getSchemes());
            $item['defaults'] = \array_diff_key($defaults, ['_arguments' => true]);
            $item['patterns'] = $route->getPatterns();
            $item['arguments'] = $defaults['_arguments'] ?? [];

            foreach ($route->getMiddlewares() as $middleware) {
                $classname = \is_string($middleware) ? $middleware : \get_class($middleware);

                if ($middleware instanceof NamedBlankMiddleware) {
                    $classname .= ':' . $middleware->getName();
                }

                $item['middlewares'][] = $classname;
            }

            if ($first) {
                return $item;
            }

            $result[] = $item;
        }

        return $result;
    }

    /**
     * @param iterable<int,Route> $routes
     *
     * @return string[]
     */
    public static function routesToNames(iterable $routes): array
    {
        $result = [];

        foreach ($routes as $route) {
            $result[] = $route instanceof Route ? $route->getName() : $route['name'];
        }

        return $result;
    }
}
