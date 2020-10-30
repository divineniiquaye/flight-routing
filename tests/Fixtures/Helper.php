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

use Flight\Routing\Interfaces\RouteInterface;

/**
 * Helper
 */
class Helper
{
    /**
     * @param iterable<int,RouteInterface> $routes
     *
     * @return array<int,array<string,mixed>>
     */
    public static function routesToArray(iterable $routes): array
    {
        $result = [];

        foreach ($routes as $route) {
            if (\is_object($controller = $route->getController())) {
                $controller = \get_class($controller);
            }

            $item                = [];
            $item['name']        = $route->getName();
            $item['path']        = $route->getPath();
            $item['domain']      = $route->getDomain();
            $item['methods']     = $route->getMethods();
            $item['handler']     = $controller;
            $item['middlewares'] = [];
            $item['schemes']     = $route->getSchemes();
            $item['defaults']    = $route->getDefaults();
            $item['patterns']    = $route->getPatterns();
            $item['arguments']   = $route->getArguments();

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
     * @param iterable<int,RouteInterface> $routes
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
