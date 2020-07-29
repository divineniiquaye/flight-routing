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

/**
 * Interface defining required router compiling capabilities.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
interface RouteMatcherInterface
{
    /**
     * Compile route matcher into regexp.
     *
     * @param RouteInterface $route
     *
     * @return RouteMatcherInterface
     */
    public function compileRoute(RouteInterface $route): self;

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
     * Returns the compiled regexp for request matching
     *
     * @param bool $domain used only if route domain was compiled, else
     *                     return an empty string
     *
     * @return string
     */
    public function getRegex(bool $domain = false): string;

    /**
     * Return the parameters found in `getRegex()` method.
     *
     * If parameters exists, but allowed not to be used when matched,
     * return a null statements each.
     * Include parameters from compiled domain if available.
     *
     * @return array<int|string,string>
     */
    public function getVariables(): array;
}
