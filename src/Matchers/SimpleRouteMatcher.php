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

namespace Flight\Routing\Matchers;

use Flight\Routing\Exceptions\UriHandlerException;
use Flight\Routing\Interfaces\RouteMatcherInterface;
use Flight\Routing\Route;
use Flight\Routing\RouteList;
use Flight\Routing\Traits\ValidationTrait;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

class SimpleRouteMatcher implements RouteMatcherInterface
{
    use ValidationTrait;

    private const URI_FIXERS = [
        '[]'  => '',
        '[/]' => '',
        '['   => '',
        ']'   => '',
        '://' => '://',
        '//'  => '/',
    ];

    /** @var SimpleRouteCompiler */
    private $compiler;

    /** @var Route[] */
    private $dynamicRoutes = [];

    /** @var array<string,Route> */
    private $staticRoutes = [];

    /**
     * @param RouteList|string $collection
     */
    public function __construct($collection)
    {
        $this->compiler = new SimpleRouteCompiler();

        $this->warmCompiler($collection);
    }

    /**
     * {@inheritdoc}
     */
    public function match(ServerRequestInterface $request): ?Route
    {
        $requestUri    = $request->getUri();
        $requestMethod = $request->getMethod();
        $resolvedPath  = \rawurldecode($this->resolvePath($request));

        // Checks if $route is a static type
        if (isset($this->staticRoutes[$resolvedPath])) {
            return $this->matchRoute($this->staticRoutes[$resolvedPath], $requestUri, $requestMethod);
        }

        foreach ($this->dynamicRoutes as $route) {
            $uriParameters = [];

            /** @var SimpleRouteCompiler $compiledRoute */
            $compiledRoute = $route->getDefaults()['_compiler'];

            // https://tools.ietf.org/html/rfc7231#section-6.5.5
            if ($this->compareUri($compiledRoute->getRegex(), $resolvedPath, $uriParameters)) {
                $foundRoute    = $this->matchRoute($route, $requestUri, $requestMethod);
                $uriParameters = \array_replace(
                    $compiledRoute->getPathVariables(),
                    $uriParameters
                );

                return $this->mergeRouteArguments($foundRoute, $uriParameters);
            }
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function buildPath(Route $route, array $substitutions): string
    {
        $compiledRoute = $this->compiler->compile($route);

        $parameters = \array_merge(
            $compiledRoute->getVariables(),
            $route->getDefaults(),
            $this->fetchOptions($substitutions, \array_keys($compiledRoute->getVariables()))
        );

        $path = '';

        //Uri without empty blocks (pretty stupid implementation)
        if (count($hostRegex = $compiledRoute->getHostTemplate()) === 1) {
            $schemes = array_keys($route->getSchemes());

            // If we have s secured scheme, it should be served
            $hostScheme   = isset($schemes['https']) ? 'https' : \end($schemes);
            $hostTemplate = $this->interpolate(\current($hostRegex), $parameters);

            $path = \sprintf('%s://%s', $hostScheme, \trim($hostTemplate, '.'));
        }

        return $path .= $this->interpolate($compiledRoute->getPathTemplate(), $parameters);
    }

    /**
     * @return SimpleRouteCompiler
     */
    public function getCompiler(): SimpleRouteCompiler
    {
        return $this->compiler;
    }

    /**
     * {@inheritdoc}
     */
    public function getCompiledRoutes()
    {
        $compiledRoutes = [$this->staticRoutes, $this->dynamicRoutes];

        if (count($compiledRoutes, COUNT_RECURSIVE) > 2) {
            return $compiledRoutes;
        }

        return false;
    }

    /**
     * @param RouteList|string $routes
     */
    private function warmCompiler($routes): void
    {
        if (\is_string($routes)) {
            list($this->staticRoutes, $this->dynamicRoutes) = require $routes;

            return;
        }

        foreach ($routes->getRoutes() as $route) {
            $compiledRoute = clone $this->compiler->compile($route);

            if (empty($compiledRoute->getPathVariables())) {
                $host = empty($compiledRoute->getHostVariables());
                $url  = \rtrim($route->getPath(), '/') ?: '/';

                // Find static host
                if ($host && !empty($compiledRoute->getHostsRegex())) {
                    $route->default('_domain', $route->getDomain());
                }

                $this->staticRoutes[$url] = $host ? $route : $route->default('_compiler', $compiledRoute);
            } else {
                $this->dynamicRoutes[] = $route->default('_compiler', $compiledRoute);
            }
        }
    }

    /**
     * @param Route        $route
     * @param UriInterface $requestUri
     * @param string       $method
     *
     * @return Route
     */
    private function matchRoute(Route $route, UriInterface $requestUri, string $method): Route
    {
        $this->assertRoute($route, $requestUri, $method);

        $hostParameters = [];
        $hostAndPort    = $requestUri->getHost();

        // Added port to host for matching ...
        if (null !== $requestUri->getPort()) {
            $hostAndPort .= ':' . $requestUri->getPort();
        }

        if (null !== $staticDomain = $route->getDefaults()['_domain'] ?? null) {
            if (!isset($staticDomain[$hostAndPort])) {
                throw $this->assertDomain($hostAndPort, $requestUri->getPath());
            }

            return $route;
        } elseif (null !== $compiledRoute = $route->getDefaults()['_compiler'] ?? null) {
            /** @var SimpleRouteCompiler $compiledRoute */
            if (!$this->compareDomain($compiledRoute->getHostsRegex(), $hostAndPort, $hostParameters)) {
                throw $this->assertDomain($hostAndPort, $requestUri->getPath());
            }

            $hostParameters = \array_replace($compiledRoute->getHostVariables(), $hostParameters);
        }

        return $this->mergeRouteArguments($route, $hostParameters);
    }

    /**
     * Interpolate string with given values.
     *
     * @param string                  $string
     * @param array<int|string,mixed> $values
     *
     * @return string
     */
    private function interpolate(string $string, array $values): string
    {
        $replaces = [];

        foreach ($values as $key => $value) {
            $replaces["<{$key}>"] = (\is_array($value) || $value instanceof \Closure) ? '' : $value;
        }

        return \strtr((string) $string, $replaces + self::URI_FIXERS);
    }

    /**
     * Fetch uri segments and query parameters.
     *
     * @param array<int|string,mixed> $parameters
     * @param array<int|string,mixed> $allowed
     *
     * @return array<int|string,mixed>
     */
    private function fetchOptions($parameters, array $allowed): array
    {
        $result = [];

        foreach ($parameters as $key => $parameter) {
            if (\is_numeric($key) && isset($allowed[$key])) {
                // this segment fetched keys from given parameters either by name or by position
                $key = $allowed[$key];
            }

            // TODO: String must be normalized here
            $result[$key] = $parameter;
        }

        return $result;
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return string
     */
    private function resolvePath(ServerRequestInterface $request): string
    {
        $requestPath = $request->getUri()->getPath();
        $basePath    = $request->getServerParams()['SCRIPT_NAME'] ?? '';

        if (
            $basePath !== $requestPath && 
            \strlen($basePath = \dirname($basePath)) > 1 && 
            $basePath !== '/index.php'
        ) {
            $requestPath = \substr($requestPath, strcmp($basePath, $requestPath)) ?: '';
        }

        return \strlen($requestPath) > 1 ? rtrim($requestPath, '/') : $requestPath;
    }

    /**
     * @param Route                         $route
     * @param array<int|string,null|string> $arguments
     *
     * @return Route
     */
    private function mergeRouteArguments(Route $route, array $arguments): Route
    {
        foreach ($arguments as $key => $value) {
            $route->argument($key, $value);
        }

        return $route;
    }

    /**
     * @param string $hostAndPort
     * @param string $requestPath
     *
     * @return UriHandlerException
     */
    private function assertDomain(string $hostAndPort, string $requestPath): UriHandlerException
    {
        return new UriHandlerException(
            \sprintf(
                'Unfortunately current domain "%s" is not allowed on requested uri [%s]',
                $hostAndPort,
                $requestPath
            ),
            400
        );
    }
}
