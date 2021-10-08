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

use Flight\Routing\Exceptions\UrlGenerationException;
use Flight\Routing\Generator\{GeneratedRoute, GeneratedUri};
use Flight\Routing\Routes\FastRoute as Route;

/**
 * This is the interface that all custom compilers for routes will depend on or implement.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
interface RouteCompilerInterface
{
    /**
     * Build all routes to avoid re-compiling and for faster route match.
     * If compiler doesn't support this functionality, return null instead.
     *
     * @see Flight\Routing\RouteMatcher implementation of this method
     *
     * @param iterable<int,Route> $routes
     */
    public function build(iterable $routes): ?GeneratedRoute;

    /**
     * Match the Route instance and compiles the current route instance.
     *
     * This method should strictly return an indexed array of three parts.
     *
     * - path regex, which starting and ending modifiers stripped off.
     *     Eg: #^\/hello\/world\/(?P<var>[^\/]+)$#sDu -----> \/hello\/world\/(?P<var>[^\/]+).
     * - hosts regex, modifies stripped of as path regex. But if more than one hosts,
     *     hosts must be imploded with a | inside a (?|...)
     * - variables, which is an unique array of path vars merged into hosts vars (if available).
     *
     * @see Flight\Routing\RouteMatcher::match() implementation
     *
     * @return array<int,mixed>
     */
    public function compile(Route $route): array;

    /**
     * Generate a URI from a named route.
     *
     * @see Flight\Routing\RouteMatcher::generateUri() implementation
     *
     * @param array<int|string,int|string> $parameters
     *
     * @throws UrlGenerationException
     */
    public function generateUri(Route $route, array $parameters): GeneratedUri;
}
