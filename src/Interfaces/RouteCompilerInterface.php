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

namespace Flight\Routing\Interfaces;

use Flight\Routing\Exceptions\UrlGenerationException;
use Flight\Routing\Generator\GeneratedUri;
use Flight\Routing\Routes\FastRoute as Route;

/**
 * This is the interface that all custom compilers for routes will depend on or implement.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
interface RouteCompilerInterface
{
    /**
     * Match the Route instance and compiles the current route instance.
     *
     * This method should strictly return an indexed array of three parts.
     *
     * - path regex, which starting and ending modifiers stripped off.
     *     Eg: #^\/hello\/world\/(?P<var>[^\/]+)$#sDu -----> \/hello\/world\/(?P<var>[^\/]+).
     * - hosts regex, modifies stripped of as path regex. But if more than one hosts,
     *     hosts must be imploded with a | inside a (?|...)
     * - variables, which is an unique array of hosts vars(if available) merged into path vars.
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
