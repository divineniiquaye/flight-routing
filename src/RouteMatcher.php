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

use Flight\Routing\Exceptions\{MethodNotAllowedException, UriHandlerException, UrlGenerationException};
use Flight\Routing\Generator\GeneratedUri;
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
    private RouteCollection $routes;
    private RouteCompilerInterface $compiler;

    /** @var array<int,mixed> */
    private ?array $compiledData = null;

    /** @var array<string,mixed> */
    private array $optimized = [];

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
        return [$this->compiler->build($this->routes), $this->getRoutes(), $this->compiler];
    }

    /**
     * @internal
     *
     * @param array<int,mixed> $data
     */
    public function __unserialize(array $data): void
    {
        [$this->compiledData, $routes, $this->compiler] = $data;
        $this->routes = RouteCollection::create($routes);
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
        return $this->optimized[$method . $uri] ??= $this->{($c = $this->compiledData) ? 'matchCached' : 'matchCollection'}($method, $uri, $c ?? $this->routes);
    }

    /**
     * {@inheritdoc}
     */
    public function generateUri(string $routeName, array $parameters = []): GeneratedUri
    {
        if (null === $optimized = &$this->optimized[$routeName] ?? null) {
            foreach ($this->routes->getRoutes() as $offset => $route) {
                if ($routeName === $route->getName()) {
                    $optimized = $offset;
                    goto generate_uri;
                }
            }

            throw new UrlGenerationException(\sprintf('Unable to generate a URL for the named route "%s" as such route does not exist.', $routeName), 404);
        }

        generate_uri:
        return $this->compiler->generateUri($this->routes->getRoutes()[$optimized], $parameters);
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
     * @return array<int,Route>
     */
    public function getRoutes(): array
    {
        return $this->routes->getRoutes();
    }

    /**
     * Tries to match a route from a set of routes.
     */
    protected function matchCollection(string $method, UriInterface $uri, RouteCollection $routes): ?Route
    {
        $requirements = [[], [], []];
        $requestPath = $uri->getPath();

        foreach ($routes->getRoutes() as $route) {
            if (!empty($staticPrefix = $route->getStaticPrefix()) && !\str_starts_with($requestPath, $staticPrefix)) {
                continue;
            }

            [$pathRegex, $hostsRegex, $variables] = $this->compiler->compile($route);

            if (!\preg_match($pathRegex, $requestPath, $matches, \PREG_UNMATCHED_AS_NULL)) {
                continue;
            }

            $hostsVar = [];
            $requiredSchemes = $route->getSchemes();

            if (!empty($hostsRegex) && !$this->matchHost($hostsRegex, $uri, $hostsVar)) {
                $requirements[1][] = $hostsRegex;

                continue;
            }

            if (!\in_array($method, $route->getMethods(), true)) {
                $requirements[0] = \array_merge($requirements[0], $route->getMethods());

                continue;
            }

            if ($requiredSchemes && !\in_array($uri->getScheme(), $requiredSchemes, true)) {
                $requirements[2] = \array_merge($requirements[2], $route->getSchemes());

                continue;
            }

            if (!empty($variables)) {
                $matchInt = 0;

                foreach ($variables as $key => $value) {
                    $route->argument($key, $matches[++$matchInt] ?? $matches[$key] ?? $hostsVar[$key] ?? $value);
                }
            }

            return $route;
        }

        return $this->assertMatch($method, $uri, $requirements);
    }

    /**
     * Tries matching routes from cache.
     */
    public function matchCached(string $method, UriInterface $uri, array $optimized): ?Route
    {
        [$requestPath, $matches, $requirements] = [$uri->getPath(), [], [[], [], []]];

        if (null !== $handler = $optimized['handler'] ?? null) {
            $matchedIds = $handler($method, $uri, $optimized, fn (int $id) => $this->routes->getRoutes()[$id] ?? null);

            if (\is_array($matchedIds)) {
                goto found_a_route_match;
            }

            return $matchedIds;
        }

        [$staticRoutes, $regexList, $variables] = $optimized;

        if (empty($matchedIds = $staticRoutes[$requestPath] ?? [])) {
            if (null === $regexList || !\preg_match($regexList, $requestPath, $matches, \PREG_UNMATCHED_AS_NULL)) {
                return null;
            }

            $matchedIds = [(int) $matches['MARK']];
        }

        found_a_route_match:
        foreach ($matchedIds as $matchedId) {
            $requiredSchemes = ($route = $this->routes->getRoutes()[$matchedId])->getSchemes();

            if (!\in_array($method, $route->getMethods(), true)) {
                $requirements[0] = \array_merge($requirements[0], $route->getMethods());

                continue;
            }

            if ($requiredSchemes && !\in_array($uri->getScheme(), $requiredSchemes, true)) {
                $requirements[2] = \array_merge($requirements[2], $route->getSchemes());

                continue;
            }

            if (\array_key_exists($matchedId, $variables)) {
                [$hostsRegex, $routeVar] = $variables[$matchedId];
                $hostsVar = [];

                if ($hostsRegex && !$this->matchHost($hostsRegex, $uri, $hostsVar)) {
                    $requirements[1][] = $hostsRegex;

                    continue;
                }

                if (!empty($routeVar)) {
                    $matchInt = 0;

                    foreach ($routeVar as $key => $value) {
                        $route->argument($key, $matches[++$matchInt] ?? $matches[$key] ?? $hostsVar[$key] ?? $value);
                    }
                }
            }

            return $route;
        }

        return $this->assertMatch($method, $uri, $requirements);
    }

    protected function matchHost(string $hostsRegex, UriInterface $uri, array &$hostsVar): bool
    {
        $hostAndPost = $uri->getHost() . (null !== $uri->getPort() ? ':' . $uri->getPort() : '');

        return (bool) \preg_match($hostsRegex, $hostAndPost, $hostsVar, \PREG_UNMATCHED_AS_NULL);
    }

    /**
     * @param array<int,mixed> $requirements
     */
    protected function assertMatch(string $method, UriInterface $uri, array $requirements)
    {
        [$requiredMethods, $requiredHosts, $requiredSchemes] = $requirements;

        if (!empty($requiredMethods)) {
            $this->assertMethods($method, $uri->getPath(), $requiredMethods);
        }

        if (!empty($requiredSchemes)) {
            $this->assertSchemes($uri, $requiredSchemes);
        }

        if (!empty($requiredHosts)) {
            $this->assertHosts($uri, $requiredHosts);
        }

        return null;
    }

    /**
     * @param array<int,string> $requiredMethods
     */
    protected function assertMethods(string $method, string $uriPath, array $requiredMethods): void
    {
        $allowedMethods = [];

        foreach (\array_unique($requiredMethods) as $requiredMethod) {
            if ($method === $requiredMethod || 'HEAD' === $requiredMethod) {
                continue;
            }

            $allowedMethods[] = $requiredMethod;
        }

        if (!empty($allowedMethods)) {
            throw new MethodNotAllowedException($allowedMethods, $uriPath, $method);
        }
    }

    /**
     * @param array<int,string> $requiredSchemes
     */
    protected function assertSchemes(UriInterface $uri, array $requiredSchemes): void
    {
        $allowedSchemes = [];

        foreach (\array_unique($requiredSchemes) as $requiredScheme) {
            if ($uri->getScheme() !== $requiredScheme) {
                $allowedSchemes[] = $requiredScheme;
            }
        }

        if (!empty($allowedSchemes)) {
            throw new UriHandlerException(
                \sprintf(
                    'Route with "%s" path is not allowed on requested uri "%s" with invalid scheme, supported scheme(s): [%s].',
                    $uri->getPath(),
                    (string) $uri,
                    \implode(', ', $allowedSchemes)
                ),
                400
            );
        }
    }

    /**
     * @param array<int,string> $requiredHosts
     */
    protected function assertHosts(UriInterface $uri, array $requiredHosts): void
    {
        $allowedHosts = 0;

        foreach ($requiredHosts as $requiredHost) {
            $hostsVar = [];

            if (!empty($requiredHost) && !$this->matchHost($requiredHost, $uri, $hostsVar)) {
                ++$allowedHosts;
            }
        }

        if ($allowedHosts > 0) {
            throw new UriHandlerException(
                \sprintf('Route with "%s" path is not allowed on requested uri "%s" as uri host is invalid.', $uri->getPath(), (string) $uri),
                400
            );
        }
    }
}
