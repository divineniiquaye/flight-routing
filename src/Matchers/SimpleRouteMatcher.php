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

use Flight\Routing\Exceptions\UrlGenerationException;
use Flight\Routing\Interfaces\RouteMatcherInterface;
use Flight\Routing\Route;
use Flight\Routing\RouteCollection;
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

    /** @var Route[] */
    protected $routes = [];

    /** @var string[] */
    protected $dynamicRoutes = [];

    /** @var array<string,string|null> */
    protected $staticRoutes = [];

    /** @var SimpleRouteCompiler */
    private $compiler;

    /**
     * @param Route[]|RouteCollection $collection
     */
    public function __construct($collection)
    {
        $this->compiler = new SimpleRouteCompiler();

        if ($collection instanceof RouteCollection) {
            $collection = $collection->getRoutes();
        }

        if ($this instanceof SimpleRouteMatcher) {
            $this->routes = $collection;
        }

        $this->warmCompiler($collection);
    }

    /**
     * {@inheritdoc}
     */
    public function match(ServerRequestInterface $request): ?Route
    {
        $resolvedPath  = \rawurldecode($this->resolvePath($request));

        // Checks if $route is a static type
        if (isset($this->staticRoutes[$resolvedPath])) {
            /** @var array<string,mixed> $matchedDomain */
            [$id, $matchedDomain] = $this->staticRoutes[$resolvedPath];

            return $this->matchRoute($this->routes[$id], $request->getUri(), $request->getMethod(), $matchedDomain);
        }

        /**
         * @var array<string,mixed> $pathVars
         * @var array<string,mixed> $matchDomain
         */
        foreach ($this->dynamicRoutes as $id => [$pathRegex, $pathVars, $matchDomain]) {
            $uriVars = [];

            // https://tools.ietf.org/html/rfc7231#section-6.5.5
            if (!$this->compareUri($pathRegex, $resolvedPath, $uriVars)) {
                continue;
            }

            $route = $this->routes[$id];
            $route->arguments(array_replace($pathVars, $uriVars));

            return $this->matchRoute($route, $request->getUri(), $request->getMethod(), $matchDomain);
        }

        return null;
    }

    /**
     * {@inheritdoc}
     *
     * @return string of fully qualified URL for named route
     */
    public function generateUri(string $routeName, array $parameters = [], array $queryParams = []): string
    {
        static $uriRoute;

        if (isset($this->routes[$routeName])) {
            $uriRoute = $this->routes[$routeName];
        } else {
            foreach ($this->routes as $route) {
                if ($routeName === $route->get('name')) {
                    $uriRoute = $route;

                    break;
                }
            }
        }

        if ($uriRoute instanceof Route) {
            return $this->resolveUri($uriRoute, $parameters, $queryParams);
        }

        throw new UrlGenerationException(
            \sprintf(
                'Unable to generate a URL for the named route "%s" as such route does not exist.',
                $routeName
            ),
            404
        );
    }

    /**
     * @return SimpleRouteCompiler
     */
    public function getCompiler(): SimpleRouteCompiler
    {
        return $this->compiler;
    }

    /**
     * @param Route[]|string $routes
     */
    protected function warmCompiler($routes): void
    {
        foreach ($routes as $index => $route) {
            $compiledRoute = clone $this->compiler->compile($route);
            $matchDomain   = [[], []];

            if (!empty($compiledRoute->getHostVariables())) {
                $matchDomain = [$compiledRoute->getHostsRegex(), $compiledRoute->getHostVariables()];
            }

            if (empty($pathVariables = $compiledRoute->getPathVariables())) {
                $url  = \rtrim($route->get('path'), '/') ?: '/';

                $this->staticRoutes[$url] = [$index, $matchDomain];

                continue;
            }

            $route->arguments($pathVariables);

            $this->dynamicRoutes[$index] = [$compiledRoute->getRegex(), $compiledRoute->getPathVariables(), $matchDomain];
        }
    }

    /**
     * This method is used to build uri, this can be overwritten.
     *
     * @param array<int|string,int|string> $parameters
     */
    protected function buildPath(Route $route, array $parameters): string
    {
        $compiledRoute = clone $this->compiler->compile($route);
        $pathRegex     = $compiledRoute->getPathTemplate();
        $hostRegex     = $path = '';

        $parameters = \array_merge(
            $compiledRoute->getVariables(),
            $route->get('defaults'),
            $this->fetchOptions($parameters, \array_keys($compiledRoute->getVariables()))
        );

        if (\count($compiledRoute->getHostTemplate()) === 1) {
            $hostRegex = \current($compiledRoute->getHostTemplate());
        }

        //Uri without empty blocks (pretty stupid implementation)
        if (!empty($hostRegex)) {
            $schemes     = $route->get('schemes');
            $schemesKeys = \array_keys($schemes);

            // If we have s secured scheme, it should be served
            $hostScheme   = isset($schemes['https']) ? 'https' : (\end($schemesKeys) ?: 'http');
            $hostTemplate = $this->interpolate($hostRegex, $parameters);

            $path = \sprintf('%s://%s', $hostScheme, \trim($hostTemplate, '.'));
        }

        return $path .= $this->interpolate($pathRegex, $parameters);
    }

    /**
     * @param mixed[] $matchedDomain
     */
    protected function matchRoute(
        Route $route,
        UriInterface $requestUri,
        string $method,
        array $matchedDomain = []
    ): Route {
        $this->assertRoute($route, $requestUri, $method);

        if (empty($matchedDomain)) {
            return $route;
        }

        $hostAndPort    = $requestUri->getHost();
        $hostParameters = [];

        [$hostRegexs, $hostVars] = $matchedDomain;

        // Added port to host for matching ...
        if (null !== $requestUri->getPort()) {
            $hostAndPort .= ':' . $requestUri->getPort();
        }

        if (!$this->compareDomain($hostRegexs, $hostAndPort, $hostParameters)) {
            throw $this->assertHost($hostAndPort, $requestUri->getPath());
        }

        return $route->arguments(\array_replace($hostVars, $hostParameters));
    }

    /**
     * @param array<int|string,int|string> $parameters
     * @param array<int|string,int|string> $queryParams
     */
    private function resolveUri(Route $route, array $parameters, array $queryParams): string
    {
        $prefix     = '.'; // Append missing "." at the beginning of the $uri.
        $createdUri = $this->buildPath($route, $parameters);

        // Making routing on sub-folders easier
        if (\strpos($createdUri, '/') !== 0) {
            $prefix .= '/';
        }

        // Incase query is added to uri.
        if (!empty($queryParams)) {
            $createdUri .= '?' . \http_build_query($queryParams);
        }

        if (\strpos($createdUri, '://') === false) {
            $createdUri = $prefix . $createdUri;
        }

        return \rtrim($createdUri, '/');
    }

    /**
     * Interpolate string with given values.
     *
     * @param array<int|string,mixed> $values
     */
    private function interpolate(string $string, array $values): string
    {
        $replaces = self::URI_FIXERS;

        foreach ($values as $key => $value) {
            $replaces["<{$key}>"] = (\is_array($value) || $value instanceof \Closure) ? '' : $value;
        }

        return \strtr($string, $replaces);
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
}
