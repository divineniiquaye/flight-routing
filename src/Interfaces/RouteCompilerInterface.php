<?php declare(strict_types=1);

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
use Flight\Routing\RouteUri as GeneratedUri;

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
     * This method should strictly return an indexed array of two parts.
     *
     * - path regex, with starting and ending modifiers. Eg #^\/hello\/world\/(?P<var>[^\/]+)$#sDu
     * - variables, which is an unique array of path variables (if available).
     *
     * @see Flight\Routing\Router::match() implementation
     *
     * @param string                        $route        the pattern to compile
     * @param array<string,string|string[]> $placeholders
     *
     * @return array<int,mixed>
     */
    public function compile(string $route, array $placeholders = []): array;

    /**
     * Generate a URI from a named route.
     *
     * @see Flight\Routing\Router::generateUri() implementation
     *
     * @param array<string,mixed>          $route
     * @param array<int|string,int|string> $parameters
     *
     * @throws UrlGenerationException if mandatory parameters are missing
     *
     * @return null|GeneratedUri should return null if this is not implemented
     */
    public function generateUri(array $route, array $parameters, int $referenceType = GeneratedUri::ABSOLUTE_PATH): ?GeneratedUri;
}
