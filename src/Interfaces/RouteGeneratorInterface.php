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

namespace Flight\Routing\Interfaces;

use Flight\Routing\Route;
use Psr\Http\Message\UriInterface;

/**
 * A fluent implementation for compiled routes data.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
interface RouteGeneratorInterface
{
    /**
     * The data got from using the route compiler's build method.
     *
     * @return mixed
     */
    public function getData();

    /**
     * Tries to match the route from compiled data.
     *
     * @param callable(int,?string,UriInterface): array{0: Route, 1: string|null} $routes
     *
     * @return array<int,int>|Route|null
     */
    public function match(string $method, UriInterface $uri, callable $routes);
}
