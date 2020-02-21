<?php

declare(strict_types=1);

/*
 * This code is under BSD 3-Clause "New" or "Revised" License.
 *
 * PHP version 7 and above required
 *
 * @category  RoutingManager
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 Biurad Group (https://biurad.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * @link      https://www.biurad.com/projects/routingmanager
 * @since     Version 0.1
 */

namespace Flight\Routing\Interfaces;

use Flight\Routing\RouteCollector;
use Flight\Routing\Route;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Interface defining required router capabilities.
 */
interface RouterInterface
{
    /**
     * Add a route.
     *
     * This method adds a route against which the underlying implementation may
     * match. Implementations MUST aggregate route instances, but MUST NOT use
     * the details to inject the underlying router until `match()`.
     * This is required to allow consumers to modify route instances before matching
     * (e.g., to provide route options, inject a name, etc.).
     *
     * The method MUST raise RuntimeException if called after `match()`
     * have already been called, to ensure integrity of the
     * router between invocations of either of those methods.
     *
     * @throws \RuntimeException when called after match() have been called.
     */
    public function addRoute(Route $route) : void;

    /**
     * Adds parameters.
     *
     * This method implements a fluent interface.
     *
     * @param array $parameters The parameters
     *
     * @return $this
     */
    public function addParameters(array $parameters);

    /**
     * Match a request against the known routes.
     *
     * Implementations will aggregate required information from the provided
     * request instance, and pass them to the underlying router implementation;
     * when done, they will then marshal a `RouteCollector` instance indicating
     * the results of the matching operation and return it to the caller.
     *
     * The caller should be implemented in such a way by passing an empty
     * parameter into results and returning an array of matched `Route` instance,
     * and the passed `$parameters`.
     *
     */
    public function match(Request $request, RouteCollector $router): array;
}
