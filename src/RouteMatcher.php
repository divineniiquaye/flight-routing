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

use Flight\Routing\Exceptions\{MethodNotAllowedException, UriHandlerException, UrlGenerationException};
use Flight\Routing\Interfaces\{RouteCompilerInterface, RouteMatcherInterface};
use Psr\Http\Message\{ServerRequestInterface, UriInterface};

/**
 * The bidirectional route matcher responsible for matching
 * HTTP request and generating url from routes.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class RouteMatcher implements RouteMatcherInterface
{
    /** @var iterable<int,Route> */
    protected $routes = [];

    /** @var array<string,array{0:int,1:string[],2:string}>> */
    protected $staticRouteMap = [];

    /** @var array<int,array> */
    protected $variableRouteData = [];

    /** @var string[] */
    protected $regexToRoutesMap = [];

    /** @var DebugRoute|null */
    protected $debug;

    /** @var RouteCompilerInterface */
    private $compiler;

    public function __construct(RouteCollection $collection)
    {
        $this->compiler = $collection->getCompiler();
        $this->routes = $collection->getIterator();

        // Load the route maps from $collection.
        [$this->staticRouteMap, $dynamicRouteMap, $this->variableRouteData] = $collection->getRouteMaps();

        if (!empty($dynamicRouteMap)) {
            // Split to prevent a too large regex error ...
            foreach (\array_chunk($dynamicRouteMap, 150, true) as $dynamicRoute) {
                $this->regexToRoutesMap[] = '~^(?|' . \implode('|', $dynamicRoute) . ')$~Ju';
            }
        }

        // Enable routes profiling ...
        $this->debug = $collection->getDebugRoute();
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
        $requestPath = $uri->getPath();

        if ('/' !== $requestPath && isset(Route::URL_PREFIX_SLASHES[$requestPath[-1]])) {
            $uri = $uri->withPath($requestPath = \substr($requestPath, 0, -1));
        }

        if (isset($this->staticRouteMap[$requestPath])) {
            [$routeId, $methods, $hostsRegex] = $this->staticRouteMap[$requestPath];
            $variables = $this->variableRouteData[$routeId];

            if (!\in_array($method, $methods, true)) {
                throw new MethodNotAllowedException($methods, $uri->getPath(), $method);
            }

            if (!empty($hostsRegex)) {
                $hostAndPost = $uri->getHost() . (null !== $uri->getPort() ? ':' . $uri->getPort() : '');

                if (!\preg_match($hostsRegex, $hostAndPost, $hostsVar)) {
                    if (!empty($this->regexToRoutesMap)) {
                        goto retry_routing;
                    }

                    throw new UriHandlerException(\sprintf('Unfortunately current host "%s" is not allowed on requested static path [%s].', $uri->getHost(), $uri->getPath()), 400);
                }

                foreach ($variables as $key => $var) {
                    if (isset($hostsVar[$key])) {
                        $variables[$key] = $hostsRegex[$key] ?? $var;
                    }
                }
            }

            return $this->matchRoute($this->routes[$routeId]->arguments($variables), $uri);
        }

        if (isset($this->regexToRoutesMap)) {
            retry_routing:
            $requestPath = $method . \strpbrk((string) $uri, '/');

            foreach ($this->regexToRoutesMap as $regexRoute) {
                if (\preg_match($regexRoute, $requestPath, $matches)) {
                    $route = $this->routes[$routeId = $matches['MARK']];
                    $variables = $this->variableRouteData[$routeId];

                    if (!empty($matches[1])) {
                        throw new MethodNotAllowedException($route->get('methods'), $uri->getPath(), $method);
                    }

                    unset($matches[0], $matches[1], $matches['MARK']);
                    $matchVar = 2; // Indexing shifted due to method and host combined in regex

                    foreach ($variables as $key => $value) {
                        $route->argument($key, $matches[$matchVar] ?? $value);

                        ++$matchVar;
                    }

                    return $this->matchRoute($route, $uri);
                }
            }
        }

        return null;
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

    /**
     * Get the profiled routes.
     */
    public function getProfile(): ?DebugRoute
    {
        return $this->debug;
    }

    protected function matchRoute(Route $route, UriInterface $uri): Route
    {
        $schemes = $route->get('schemes');

        if (!empty($schemes) && !\array_key_exists($uri->getScheme(), $schemes)) {
            throw new UriHandlerException(\sprintf('Unfortunately current scheme "%s" is not allowed on requested uri [%s].', $uri->getScheme(), $uri->getPath()), 400);
        }

        if (null !== $this->debug) {
            $this->debug->setMatched($route->get('name'));
        }

        return $route;
    }
}
