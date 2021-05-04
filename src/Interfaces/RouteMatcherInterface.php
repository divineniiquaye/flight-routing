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
use Psr\Http\Message\ServerRequestInterface;

/**
 * Interface defining required router compiling capabilities.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
interface RouteMatcherInterface
{
    /**
     * Marshals a route result based on the results of matching URL from set of routes.
     *
     * @param RouteListInterface     $routes
     * @param ServerRequestInterface $request
     *
     * @return null|RouteInterface
     */
    public function match(RouteListInterface $routes, ServerRequestInterface $request): ?RouteInterface;

    /**
     * Generate a URI from the named route.
     *
     * Takes the named route path and any substitutions, then attempts to generate a
     * URI from it.
     *
     * The URI generated MUST NOT be escaped. If you wish to escape any part of
     * the URI, this should be performed afterwards; consider passing the URI
     * to league/uri to encode it.
     *
     * @param RouteInterface     $route
     * @param array<mixed,mixed> $substitutions key => value option pairs to pass to the
     *                                          router for purposes of generating a URI; takes precedence over options
     *                                          present in route used to generate URI
     *
     * @throws UrlGenerationException if a parameter value does not match its regex
     *
     * @return string
     */
    public function buildPath(RouteInterface $route, array $substitutions): string;

    /**
     * This warms up compiler used to compile route, to increase performance.
     *
     * Implement this fluent method or return it as false.
     *
     * @param RouteListInterface|string $routes routes collection
     *                                          or a file containing compiled routes
     *
     * @return mixed return false if not implemented or null if $routes is string
     */
    public function warmCompiler($routes);
}
