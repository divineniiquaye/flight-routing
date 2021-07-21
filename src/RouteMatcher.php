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

namespace Flight\Routing;

use Flight\Routing\Exceptions\{UriHandlerException, UrlGenerationException};
use Flight\Routing\Interfaces\{RouteCompilerInterface, RouteMapInterface, RouteMatcherInterface};
use Psr\Http\Message\{ServerRequestInterface, UriInterface};

/**
 * The bidirectional route matcher responsible for matching
 * HTTP request and generating url from routes.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class RouteMatcher implements RouteMatcherInterface, \Countable
{
    /** @var array<int,Route> */
    protected $routes;

    /** @var array */
    protected $staticRouteMap;

    /** @var array */
    protected $dynamicRouteMap;

    /** @var DebugRoute|null */
    protected $debug;

    /** @var RouteCompilerInterface */
    private $compiler;

    public function __construct(RouteMapInterface $collection)
    {
        $this->compiler = $collection->getCompiler();

        $this->routes = $collection['routes'] ?? [];
        $this->staticRouteMap = $collection['staticRoutesMap'] ?? [];
        $this->dynamicRouteMap = $collection['dynamicRoutesMap'] ?? [];
    }

    /**
     * Get the total number of routes.
     */
    public function count(): int
    {
        return \count($this->routes);
    }

    /**
     * {@inheritdoc}
     */
    public function matchRequest(ServerRequestInterface $request): ?Route
    {
        $requestUri = $request->getUri();

        // Resolve request path to match sub-directory or /index.php/path
        if (!empty($pathInfo = $request->getServerParams()['PATH_INFO'] ?? '')) {
            $requestUri = $requestUri->withPath($pathInfo);
        }

        return $this->match($request->getMethod(), $requestUri);
    }

    /**
     * {@inheritdoc}
     */
    public function match(string $method, UriInterface $uri): ?Route
    {
        $pathInfo = $uri->getPath();
        $requestPath = \rtrim($pathInfo, Route::URL_PREFIX_SLASHES[$pathInfo[-1]] ?? '/') ?: '/';

        if (isset($this->staticRouteMap[$requestPath])) {
            [$routeId, $hostsRegex, $variables] = $this->staticRouteMap[$requestPath];
            $route = $this->routes[$routeId]->match($method, $uri);

            if (null !== $hostsRegex) {
                $variables = $this->matchStaticRouteHost($uri, $hostsRegex, $variables);

                if (null === $variables) {
                    if (!empty($this->dynamicRouteMap)) {
                        goto retry_routing;
                    }

                    throw new UriHandlerException(\sprintf('Unfortunately current host "%s" is not allowed on requested static path [%s].', $uri->getHost(), $uri->getPath()), 400);
                }
            }

            return empty($variables) ? $route : $route->arguments($variables);
        }

        retry_routing:
        if ($pathInfo !== $requestPath) {
            $uri = $uri->withPath($requestPath);
        }

        return $this->matchVariableRoute($method, $uri);
    }

    /**
     * {@inheritdoc}
     */
    public function generateUri(string $routeName, array $parameters = []): GeneratedUri
    {
        foreach ($this->routes as $route) {
            if ($routeName === $route->get('name')) {
                $defaults = $route->get('defaults');
                unset($defaults['_arguments']);

                return $this->compiler->generateUri($route, $parameters, $defaults);
            }
        }

        throw new UrlGenerationException(\sprintf('Unable to generate a URL for the named route "%s" as such route does not exist.', $routeName), 404);
    }

    public function getCompiler(): RouteCompilerInterface
    {
        return $this->compiler;
    }

    protected function matchVariableRoute(string $method, UriInterface $uri): ?Route
    {
        $requestPath = \strpbrk((string) $uri, '/');

        foreach ($this->dynamicRouteMap[0] ?? [] as $regex) {
            if (!\preg_match($regex, $requestPath, $matches)) {
                continue;
            }

            $route = $this->routes[$routeId = (int) $matches['MARK']];
            $matchVar = 0;

            foreach ($this->dynamicRouteMap[1][$routeId] ?? [] as $key => $value) {
                $route->argument($key, $matches[++$matchVar] ?? $value);
            }

            return $route->match($method, $uri);
        }

        return null;
    }

    /**
     * @param array<string,string|null> $variables
     *
     * @return array<string,string|null>|null
     */
    protected function matchStaticRouteHost(UriInterface $uri, string $hostsRegex, array $variables): ?array
    {
        $hostAndPost = $uri->getHost() . (null !== $uri->getPort() ? ':' . $uri->getPort() : '');

        if (!\preg_match($hostsRegex, $hostAndPost, $hostsVar)) {
            return null;
        }

        foreach ($variables as $key => $var) {
            if (isset($hostsVar[$key])) {
                $variables[$key] = $hostsVar[$key] ?? $var;
            }
        }

        return $variables;
    }
}
