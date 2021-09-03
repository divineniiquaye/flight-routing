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

use Flight\Routing\Routes\FastRoute as Route;

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
            $item = $route->getData();

            if (\is_object($item['handler'])) {
                $item['handler'] = \get_class($item['handler']);
            }

            if ($route instanceof Route) {
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
