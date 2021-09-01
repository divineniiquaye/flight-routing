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

use Flight\Routing\DomainRoute;
use Flight\Routing\FastRoute as Route;

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
            if (\is_object($controller = $route->getHandler())) {
                $controller = \get_class($controller);
            }

            $item = [];
            $item['name'] = $route->getName();
            $item['path'] = $route->getPath();
            $item['methods'] = $route->getMethods();
            $item['handler'] = $controller;
            $item['defaults'] = $route->getDefaults();
            $item['patterns'] = $route->getPatterns();
            $item['arguments'] = $route->getArguments();

            if ($route instanceof DomainRoute) {
                $item['hosts'] = $route->getHosts();
                $item['schemes'] = $route->getSchemes();
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
            $result[] = $route->getName();
        }

        return $result;
    }
}
