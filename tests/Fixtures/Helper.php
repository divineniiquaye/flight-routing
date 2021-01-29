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
 * Helper
 */
class Helper
{
    /**
     * @param iterable<int,Route> $routes
     *
     * @return array<int,array<string,mixed>>
     */
    public static function routesToArray(iterable $routes): array
    {
        $result = $arguments = [];

        foreach ($routes as $route) {
            if (\is_object($controller = $route->getController())) {
                $controller = \get_class($controller);
            }

            $defaults = $route->getDefaults();

            if (isset($defaults['_arguments'])) {
                $arguments = $defaults['_arguments'];
                unset($defaults['_arguments']);
            }

            $item                = [];
            $item['name']        = $route->getName();
            $item['path']        = $route->getPath();
            $item['domain']      = \array_keys($route->getDomain());
            $item['methods']     = \array_keys($route->getMethods());
            $item['handler']     = $controller;
            $item['middlewares'] = [];
            $item['schemes']     = \array_keys($route->getSchemes());
            $item['defaults']    = $defaults;
            $item['patterns']    = $route->getPatterns();
            $item['arguments']   = $arguments;

            foreach ($route->getMiddlewares() as $middleware) {
                $classname = \is_string($middleware) ? $middleware : \get_class($middleware);

                if ($middleware instanceof NamedBlankMiddleware) {
                    $classname .= ':' . $middleware->getName();
                }

                $item['middlewares'][] = $classname;
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
            $result[] = $route->getName();
        }

        return $result;
    }
}
