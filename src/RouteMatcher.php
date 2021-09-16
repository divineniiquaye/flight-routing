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
    /** @var array<int,Route>|array<int,mixed> */
    private $routes;

    /** @var RouteCompilerInterface */
    private $compiler;

    /** @var string|null */
    private $generatedRegex;

    /**
     * @var callable
     *
     * @internal Returns an optimised routes data.
     */
    private $beforeSerialization = [Generator\RegexGenerator::class, 'beforeCaching'];

    /**
     * @param RouteCompilerInterface|null $compiler
     */
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
     * @param array<string,mixed>
     */
    public function __unserialize(array $data): void
    {
        [$this->generatedRegex, $this->routes, $this->compiler] = $data;
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

        if (null === $this->generatedRegex) {
            foreach ($this->routes as $route) {
                [$pathRegex, $hostsRegex, $variables] = $this->compiler->compile($route);

                if ($pathRegex === $requestPath || 1 === \preg_match('#^' . $pathRegex . '$#u', $requestPath, $matches)) {
                    if (empty($variables)) {
                        return $route->match($method, $uri);
                    }

                    return self::doMatch($route, $uri, $hostsRegex, [$method, $variables, $matches ?? []]);
                }
            }
        } elseif (1 === \preg_match($this->generatedRegex, $requestPath, $matches, \PREG_UNMATCHED_AS_NULL)) {
            [$route, $hostsRegex, $variables] = $this->routes[$matches['MARK']];

            return self::doMatch($route, $uri, $hostsRegex, [$method, $variables, \array_filter($matches)]);
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function generateUri(string $routeName, array $parameters = []): GeneratedUri
    {
        foreach ($this->routes as $route) {
            if (!$route instanceof Route) {
                $route = $route[0];
            }

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
        $routes = $this->routes;

        if (null !== $this->generatedRegex) {
            \array_walk($routes, static function (&$data): void {
                $data = $data[0];
            });
        }

        return $routes;
    }

    /**
     * @param array<int,mixed> $routeData
     */
    private static function doMatch(Route $route, UriInterface $uri, ?string $hostsRegex, array $routeData): Route
    {
        [$method, $variables, $matches] = $routeData;
        $matchVar = 0;

        if (!empty($hostsRegex)) {
            $hostAndPost = $uri->getHost() . (null !== $uri->getPort() ? ':' . $uri->getPort() : '');

            if (1 !== \preg_match('#^' . $hostsRegex . '$#i', $hostAndPost, $hostsVar)) {
                throw new UriHandlerException(\sprintf('Unfortunately current host "%s" is not allowed on requested path [%s].', $uri->getHost(), $uri->getPath()), 400);
            }
        }

        foreach ($variables as $key => $value) {
            $route->argument($key, $matches[++$matchVar] ?? $matches[$key] ?? $hostsVar[$key] ?? $value);
        }

        return $route->match($method, $uri);
    }
}
