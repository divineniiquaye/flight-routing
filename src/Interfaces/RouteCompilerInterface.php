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

use Flight\Routing\{CompiledRoute, GeneratedUri, Route};
use Flight\Routing\Exceptions\UrlGenerationException;

/**
 * This is the interface that all custom compilers for routes will depend on or implement.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
interface RouteCompilerInterface
{
    /**
     * Match the Route instance and compiles the current route instance.
     */
    public function compile(Route $route): CompiledRoute;

    /**
     * Generate a URI from a named route.
     *
     * @see Flight\Routing\RouteMatcher::generateUri() implementation
     *
     * @param array<int|string,int|string> $parameters
     * @param array<int|string,int|string> $defaults
     *
     * @throws UrlGenerationException
     */
    public function generateUri(Route $route, array $parameters, array $defaults = []): GeneratedUri;
}
