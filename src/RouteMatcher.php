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
use Flight\Routing\Generator\GeneratedUri;
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
    /** @var array<int,Route>|array<int,array<int,mixed>> */
    private array $routes;

    private RouteCompilerInterface $compiler;

    /** @var array<int,mixed>|null */
    private ?array $compiledData = null;

    /**
     * @var callable
     *
     * @internal returns an optimised routes data
     */
    private $beforeSerialization = [Generator\RegexGenerator::class, 'beforeCaching'];

    public function __construct(RouteCollection $collection, RouteCompilerInterface $compiler = null)
    {
        $this->compiler = $compiler ?? new RouteCompiler();
        $this->routes = $collection->getRoutes();
    }

    /**
     * @internal
     */
    public function __serialize(): array
    {
        return ($this->beforeSerialization)($this->compiler, $this->getRoutes());
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
        $requestPath = $uri->getPath();

        if (\array_key_exists($requestPath[-1], BaseRoute::URL_PREFIX_SLASHES)) {
            $requestPath = \substr($requestPath, 0, -1) ?: '/';
        }

        if (null === $nextHandler = $this->compiledData) {
            /** @var Route $route */
            foreach ($this->routes as $route) {
                [$pathRegex, $hostsRegex, $variables] = $this->compiler->compile($route);

                if(\preg_match('{^' . $pathRegex . '$}u', $requestPath, $matches, \PREG_UNMATCHED_AS_NULL)) {
                    return static::doMatch($method, $uri, [$hostsRegex, $variables, $route], $matches);
                }
            }

            goto route_not_found;
        }

        [$regexList, $dynamicRoutes] = $nextHandler;

        while (\preg_match($regexList, $requestPath, $matches, \PREG_UNMATCHED_AS_NULL)) {
            foreach ($dynamicRoutes[$m = (int) $matches['MARK']] as $routeData) {
                $routeData[] = $this->routes[$m];

                return static::doMatch($method, $uri, $routeData, $matches ?? []);
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
        /** @var Route $route */
        foreach ($this->routes as $route) {
            if ($routeName === $route->get('name')) {
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
     * @return Route[]
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * @param array<int,mixed> $routeData
     * @param array<int|string,mixed> $matches
     */
    private static function doMatch(string $method, UriInterface $uri, array $routeData, array $matches): Route
    {
        /** @var Route $matchedRoute */
        [$hostsRegex, $variables, $matchedRoute] = $routeData;
        $matchVar = 0;

        if ([] === $variables) {
            return $matchedRoute->match($method, $uri);
        }

        $hostsVar = [];

        if (null !== $hostsRegex) {
            $hostAndPost = $uri->getHost() . (null !== $uri->getPort() ? ':' . $uri->getPort() : '');

            if (1 !== \preg_match('{^' . $hostsRegex . '$}i', $hostAndPost, $hostsVar, \PREG_UNMATCHED_AS_NULL)) {
                throw new UriHandlerException(\sprintf('Unfortunately current host "%s" is not allowed on requested path [%s].', $hostAndPost, $uri->getPath()), 400);
            }
        }

        foreach ($variables as $key => $value) {
            $matchedRoute->argument($key, $matches[++$matchVar] ?? $matches[$key] ?? $hostsVar[$key] ?? $value);
        }

        return $matchedRoute->match($method, $uri);
    }
}
