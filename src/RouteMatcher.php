<?php

declare(strict_types=1);

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

namespace Flight\Routing;

use Flight\Routing\Routes\{FastRoute as Route, Route as BaseRoute};
use Flight\Routing\Exceptions\{UriHandlerException, UrlGenerationException};
use Flight\Routing\Generator\{GeneratedRoute, GeneratedUri};
use Flight\Routing\Interfaces\{RouteCompilerInterface, RouteMatcherInterface};
use Psr\Http\Message\{ServerRequestInterface, UriInterface};

/**
 * The bidirectional route matcher responsible for matching
 * HTTP request and generating url from routes.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
final class RouteMatcher implements RouteMatcherInterface
{
    /** @var RouteCollection|\SplFixedArray<Route> */
    private \Traversable $routes;

    private RouteCompilerInterface $compiler;

    private ?GeneratedRoute $compiledData = null;

    public function __construct(RouteCollection $collection, RouteCompilerInterface $compiler = null)
    {
        $this->compiler = $compiler ?? new RouteCompiler();
        $this->routes = $collection;
    }

    /**
     * @internal
     */
    public function __serialize(): array
    {
        $routes = $this->getRoutes();

        return [$this->compiler->build($routes), $routes->getRoutes(), $this->compiler];
    }

    /**
     * @internal
     *
     * @param array<int,mixed> $data
     */
    public function __unserialize(array $data): void
    {
        [$this->compiledData, $this->routes, $this->compiler] = $data;
    }

    public static function matchHost(string $hostsRegex, UriInterface $uri): array
    {
        $hostAndPost = $uri->getHost() . (null !== $uri->getPort() ? ':' . $uri->getPort() : '');

        if (!\preg_match('{^' . $hostsRegex . '$}i', $hostAndPost, $hostsVar, \PREG_UNMATCHED_AS_NULL)) {
            throw new UriHandlerException(\sprintf('Unfortunately current host "%s" is not allowed on requested path [%s].', $hostAndPost, $uri->getPath()), 400);
        }

        return $hostsVar;
    }

    /**
     * {@inheritdoc}
     */
    public function matchRequest(ServerRequestInterface $request): ?Route
    {
        $requestUri = $request->getUri();

        // Resolve request path to match sub-directory or /index.php/path
        if ('' !== ($pathInfo = $request->getServerParams()['PATH_INFO'] ?? '') && $pathInfo !== $requestUri->getPath()) {
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

        if (\array_key_exists($requestPath[-1], BaseRoute::URL_PREFIX_SLASHES)) {
            $requestPath = \substr($requestPath, 0, -1) ?: '/';
        }

        if (null !== $this->compiledData) {
            if (null === $routeData = $this->compiledData->matchRoute($requestPath, $uri)) {
                goto route_not_found;
            }

            if (empty($routeData[1])) {
                return $this->routes[$routeData[0]]->match($method, $uri);
            }

            return $this->routes[$routeData[0]]->arguments($routeData[1])->match($method, $uri);
        }

        foreach ($this->routes as $route) {
            [$pathRegex, $hostsRegex, $variables] = $this->compiler->compile($route);

            if (\preg_match('{^' . $pathRegex . '$}u', $requestPath, $matches, \PREG_UNMATCHED_AS_NULL)) {
                if (!empty($variables)) {
                    $matchInt = 0;
                    $hostsVar = empty($hostsRegex) ? [] : static::matchHost($hostsRegex, $uri);

                    foreach ($variables as $key => $value) {
                        $route->argument($key, $matches[++$matchInt] ?? $matches[$key] ?? $hostsVar[$key] ?? $value);
                    }
                }

                return $route->match($method, $uri);
            }
        }

        route_not_found:
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function generateUri(string $routeName, array $parameters = []): GeneratedUri
    {
        foreach ($this->routes as $route) {
            if ($routeName === $route->getName()) {
                return $this->compiler->generateUri($route, $parameters);
            }
        }

        throw new UrlGenerationException(\sprintf('Unable to generate a URL for the named route "%s" as such route does not exist.', $routeName), 404);
    }

    /**
     * Get the compiler associated with this class.
     */
    public function getCompiler(): RouteCompilerInterface
    {
        return $this->compiler;
    }

    /**
     * Get the routes associated with this class.
     *
     * @return RouteCollection|\SplFixedArray<Route>
     */
    public function getRoutes(): \Traversable
    {
        return $this->routes;
    }
}
