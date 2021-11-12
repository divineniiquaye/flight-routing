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
use Flight\Routing\Interfaces\{RouteCompilerInterface, RouteGeneratorInterface, RouteMatcherInterface};
use Psr\Http\Message\{ServerRequestInterface, UriInterface};

/**
 * The bidirectional route matcher responsible for matching
 * HTTP request and generating url from routes.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class RouteMatcher implements RouteMatcherInterface
{
    /** @var RouteCollection|array<int,Route> */
    private $routes;

    private RouteCompilerInterface $compiler;

    private ?RouteGeneratorInterface $compiledData = null;

    public function __construct(RouteCollection $collection, RouteCompilerInterface $compiler = null)
    {
        $this->compiler = $compiler ?? new RouteCompiler();
        ($this->routes = $collection)->buildRoutes();
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
        $optimizedRoute = $this->compiledData ?? $this->matchCollection($method, $uri, $this->routes);

        if ($optimizedRoute instanceof RouteGeneratorInterface) {
            $matchedRoute = $optimizedRoute->match($method, $uri, \Closure::fromCallable([$this, 'doMatch']));

            if (\is_array($matchedRoute)) {
                $requirements = [[], [], []];

                foreach ($matchedRoute as $matchedId) {
                    $requirements[0] = \array_merge($requirements[0], $this->routes[$matchedId]->getMethods());
                    $requirements[1][] = \key($optimizedRoute->getData()[2][$method][$matchedId] ?? []);
                    $requirements[2] = \array_merge($requirements[2], $this->routes[$matchedId]->getSchemes());
                }

                return $this->assertMatch($method, $uri, $requirements);
            }

            return $matchedRoute;
        }

        return $optimizedRoute;
    }

    /**
     * {@inheritdoc}
     */
    public function generateUri(string $routeName, array $parameters = []): GeneratedUri
    {
        foreach ($this->getRoutes() as $route) {
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
     * @return array<int,Route>
     */
    public function getRoutes(): array
    {
        $routes = $this->routes;

        if ($routes instanceof RouteCollection) {
            return $routes->getRoutes();
        }

        return $routes;
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
            $routeData = $route->getData();

            if (!empty($hostsRegex) && !$this->matchHost($hostsRegex, $uri, $hostsVar)) {
                $requirements[1][] = $hostsRegex;

                continue;
            }

            if (!\array_key_exists($method, $routeData['methods'] ?? [])) {
                $requirements[0] = \array_merge($requirements[0], $route->getMethods());

                continue;
            }

            if (isset($routeData['schemes']) && !\array_key_exists($uri->getScheme(), $routeData['schemes'])) {
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

    protected function matchHost(string $hostsRegex, UriInterface $uri, array &$hostsVar): bool
    {
        $hostAndPost = $uri->getHost() . (null !== $uri->getPort() ? ':' . $uri->getPort() : '');

        return (bool) \preg_match($hostsRegex, $hostAndPost, $hostsVar, \PREG_UNMATCHED_AS_NULL);
    }

    /**
     * @return array<int,mixed>
     */
    protected function doMatch(int $matchedId, ?string $domain, UriInterface $uri): array
    {
        $hostsVar = [];

        if (!empty($domain) && !$this->matchHost($domain, $uri, $hostsVar)) {
            $hostsVar = null;
        }

        return [$this->routes[$matchedId], $hostsVar];
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

            if (!empty($requiredHosts) && !$this->matchHost($requiredHost, $uri, $hostsVar)) {
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
