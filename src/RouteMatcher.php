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
use Flight\Routing\Interfaces\{RouteCompilerInterface, RouteMatcherInterface, UrlGeneratorInterface};
use Psr\Http\Message\{ServerRequestInterface, UriInterface};

/**
 * The bidirectional route matcher responsible for matching
 * HTTP request and generating url from routes.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class RouteMatcher implements RouteMatcherInterface, UrlGeneratorInterface
{
    private RouteCompilerInterface $compiler;

    /** @var RouteCollection|array<int,Route> */
    private $routes;

    /** @var array<int,mixed> */
    private ?array $compiledData = null;

    /** @var array<int|string,mixed> */
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
        [$this->compiledData, $this->routes, $this->compiler] = $data;
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
    public function generateUri(string $routeName, array $parameters = [], int $referenceType = GeneratedUri::ABSOLUTE_PATH): GeneratedUri
    {
        if (!$optimized = &$this->optimized[$routeName] ?? null) {
            foreach ($this->getRoutes() as $offset => $route) {
                if ($routeName === $route->getName()) {
                    if (null === $matched = $this->compiler->generateUri($route, $parameters, $referenceType)) {
                        throw new UrlGenerationException(\sprintf('The route compiler class does not support generating uri for named route: %s', $routeName));
                    }

                    $optimized = $offset; // Cache the route index ...

                    return $matched;
                }
            }

            throw new UrlGenerationException(\sprintf('Unable to generate a URL for the named route "%s" as such route does not exist.', $routeName), 404);
        }

        return $this->compiler->generateUri($this->getRoutes()[$optimized], $parameters, $referenceType);
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
        if (\is_array($routes = $this->routes)) {
            return $routes;
        }

        return $routes->getRoutes();
    }

    /**
     * Tries to match a route from a set of routes.
     */
    protected function matchCollection(string $method, UriInterface $uri, RouteCollection $routes): ?Route
    {
        $requirements = [];
        $requestPath = \rawurldecode($uri->getPath()) ?: '/';
        $requestScheme = $uri->getScheme();

        foreach ($routes->getRoutes() as $offset => $route) {
            if (!empty($staticPrefix = $route->getStaticPrefix()) && !\str_starts_with($requestPath, $staticPrefix)) {
                continue;
            }

            [$pathRegex, $hostsRegex, $variables] = $this->optimized[$offset] ??= $this->compiler->compile($route);
            $hostsVar = [];

            if (!\preg_match($pathRegex, $requestPath, $matches, \PREG_UNMATCHED_AS_NULL)) {
                continue;
            }

            if (!$route->hasMethod($method)) {
                $requirements[0] = \array_merge($requirements[0] ?? [], $route->getMethods());
                continue;
            }

            if (!$route->hasScheme($requestScheme)) {
                $requirements[1] = \array_merge($requirements[1] ?? [], $route->getSchemes());
                continue;
            }

            if (empty($hostsRegex) || $this->matchHost($hostsRegex, $uri, $hostsVar)) {
                if (!empty($variables)) {
                    $matchInt = 0;

                    foreach ($variables as $key => $value) {
                        $route->argument($key, $matches[++$matchInt] ?? $matches[$key] ?? $hostsVar[$key] ?? $value);
                    }
                }

                return $route;
            }

            $requirements[2][] = $hostsRegex;
        }

        return $this->assertMatch($method, $uri, $requirements);
    }

    /**
     * Tries matching routes from cache.
     */
    public function matchCached(string $method, UriInterface $uri, array $optimized): ?Route
    {
        [$staticRoutes, $regexList, $variables] = $optimized;
        $requestPath = \rawurldecode($uri->getPath()) ?: '/';
        $requestScheme = $uri->getScheme();
        $requirements = $matches = [];
        $index = 0;

        if (null === $matchedIds = $staticRoutes[$requestPath] ?? (!$regexList || 1 !== \preg_match($regexList, $requestPath, $matches, \PREG_UNMATCHED_AS_NULL) ? null : [(int) $matches['MARK']])) {
            return null;
        }

        do {
            $route = $this->routes[$i = $matchedIds[$index]];

            if (!$route->hasMethod($method)) {
                $requirements[0] = \array_merge($requirements[0] ?? [], $route->getMethods());
                continue;
            }

            if (!$route->hasScheme($requestScheme)) {
                $requirements[1] = \array_merge($requirements[1] ?? [], $route->getSchemes());
                continue;
            }

            if (!\array_key_exists($i, $variables)) {
                return $route;
            }

            [$hostsRegex, $routeVar] = $variables[$i];
            $hostsVar = [];

            if (empty($hostsRegex) || $this->matchHost($hostsRegex, $uri, $hostsVar)) {
                if (!empty($routeVar)) {
                    $matchInt = 0;

                    foreach ($routeVar as $key => $value) {
                        $route->argument($key, $matches[++$matchInt] ?? $matches[$key] ?? $hostsVar[$key] ?? $value);
                    }
                }

                return $route;
            }

            $requirements[2][] = $hostsRegex;
        } while (isset($matchedIds[++$index]));

        return $this->assertMatch($method, $uri, $requirements);
    }

    protected function matchHost(string $hostsRegex, UriInterface $uri, array &$hostsVar): bool
    {
        $hostAndPort = $uri->getHost();

        if ($uri->getPort()) {
            $hostAndPort .= ':' . $uri->getPort();
        }

        if ($hostsRegex === $hostAndPort) {
            return true;
        }

        if (!\str_contains($hostsRegex, '^')) {
            $hostsRegex = '#^' . $hostsRegex . '$#ui';
        }

        return 1 === \preg_match($hostsRegex, $hostAndPort, $hostsVar, \PREG_UNMATCHED_AS_NULL);
    }

    /**
     * @param array<int,mixed> $requirements
     */
    protected function assertMatch(string $method, UriInterface $uri, array $requirements)
    {
        if (!empty($requirements)) {
            if (isset($requirements[0])) {
                $this->assertMethods($method, $uri->getPath(), $requirements[0]);
            }

            if (isset($requirements[1])) {
                $this->assertSchemes($uri, $requirements[1]);
            }

            if (isset($requirements[2])) {
                $this->assertHosts($uri, $requirements[2]);
            }
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
