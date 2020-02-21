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

namespace Flight\Routing;

use RuntimeException;
use BiuradPHP\Http\Exceptions\ClientExceptions;
use Flight\Routing\Interfaces\RouterInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Router implementation bridging matches.
 */
class RouteMatcher implements RouterInterface
{
    use Concerns\Validations;

    /**
     * Routes to inject into the underlying RouteCollector.
     *
     * @var Route[]
     */
    private $routesToInject = [];

    /**
     * Add a route to the collection.
     *
     * Uses the HTTP methods associated and the path, and uses the path as
     * the name (to allow later lookup).
     */
    public function addRoute(Route $route): void
    {
        array_push($this->routesToInject, $route);

        if (null === $route) {
            throw new RuntimeException('Failed to add route');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function match(Request $request, RouteCollector $router): array
    {
        // Inject any pending routes
        $path   = $request->getUri()->getPath();
        $domain = $request->getUri()->getHost();
        $method = $request->getMethod();

        $results = [];
        sort($this->routesToInject, SORT_DESC);

        // Inject queued Route instances into the underlying router.
        foreach ($this->routesToInject as $index => $route) {
            $parameters = [];
            $router->nameLookup($route);

            // Get Compiled Routes and match it.
            $match = new RouteCompiler($route, $request, $this->parameters);

            // Throw and exception if url is not found no request method.
            if (
                ! $this->compareMethod($route->getMethod(), $method) &&
                $this->compareUri($match->getRegex(), $path, $parameters)
            ) {
                throw new ClientExceptions\MethodNotAllowedException();
            }

            // Let's match the routes
            if (
                $this->compareMethod($route->getMethod(), $method) &&
                $this->compareDomain($match->getHostRegex(), $domain) &&
                $this->compareUri($match->getRegex(), $path, $parameters)
            ) {
                $results[] = [$route, $parameters];
            }
        }

        return !empty($results) ? $results : [null];
    }
}
