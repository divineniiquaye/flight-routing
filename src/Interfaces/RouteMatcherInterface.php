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
use Flight\Routing\Route;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * Interface defining required router compiling capabilities.
 *
 * This fluent implementation should contain a `constructor` method.
 * The implementation should be writen to receive a RouteCollection instance
 * and a route instances when matcher dumper instance is used by the router.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
interface RouteMatcherInterface
{
    /**
     * Marshals a route result based on the results of matching URL from set of routes.
     *
     * @param ServerRequestInterface $request
     *
     * @return null|Route
     */
    public function match(ServerRequestInterface $request);

    /**
     * Generate a URI from the named route.
     *
     * Takes the named route and any parameters, and attempts to generate a
     * URI from it. Additional router-dependent query may be passed.
     *
     * Once there are no missing parameters in the URI we will encode
     * the URI and prepare it for returning to the user. If the URI is supposed to
     * be absolute, we will return it as-is. Otherwise we will remove the URL's root.
     *
     * @param string                       $routeName   route name
     * @param array<int|string,int|string> $parameters  key => value option pairs to pass to the
     *                                                  router for purposes of generating a URI;
     *                                                  takes precedence over options
     *                                                  present in route used to generate URI
     * @param array<int|string,int|string> $queryParams Optional query string parameters
     *
     * @throws UrlGenerationException if the route name is not known
     *                                or a parameter value does not match its regex
     *
     * @return string|UriInterface of fully qualified URL for named route
     */
    public function generateUri(string $routeName, array $parameters = [], array $queryParams = []);
}
