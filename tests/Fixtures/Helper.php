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

namespace Flight\Routing\Tests\Fixtures;

use Flight\Routing\Routes\{DomainRoute, FastRoute as Route};

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
            $item = [];
            $item['name'] = $route->getName();
            $item['path'] = $route->getPath();
            $item['methods'] = $route->getMethods();

            if ($route instanceof DomainRoute) {
                $item['schemes'] = $route->getSchemes();
                $item['hosts'] = $route->getHosts();
            }

            if (\is_object($handler = $route->getHandler())) {
                $handler = \get_class($handler);
            }

            $item['handler'] = $handler;
            $item['arguments'] = $route->getArguments();
            $item['patterns'] = $route->getPatterns();
            $item['defaults'] = $route->getDefaults();

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
