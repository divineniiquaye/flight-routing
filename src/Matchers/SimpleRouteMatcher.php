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

    /** @var SimpleRouteCompiler */
    private $compiler;

    /**
     * @param Route[]|RouteCollection $collection
     */
    public function __construct($collection)
    {
        if ($collection instanceof RouteCollection) {
            $collection = $collection->getRoutes();
        }

        $this->routes   = $collection;
        $this->compiler = new SimpleRouteCompiler();
    }

    /**
     * {@inheritdoc}
     */
    public function match(ServerRequestInterface $request): ?Route
    {
        $requestUri    = $request->getUri();
        $requestMethod = $request->getMethod();
        $resolvedPath  = \rawurldecode($this->resolvePath($request));

        foreach ($this->routes as $route) {
            $compiledRoute = clone $this->compiler->compile($route);
            $matchDomain   = [];

            if (!empty($compiledRoute->getHostVariables())) {
                $matchDomain = [$compiledRoute->getHostsRegex(), $compiledRoute->getHostVariables()];
            }

            $staticUrl = \rtrim($route->get('path'), '/') ?: '/';
            $pathVars  = $compiledRoute->getPathVariables();

            // Checks if $route is a static type
            if ($staticUrl === $resolvedPath && empty($pathVars)) {
                return $this->matchRoute($route, $requestUri, $requestMethod, $matchDomain);
            }

            $uriVars   = [];
            $pathRegex = $compiledRoute->getRegex();

            // https://tools.ietf.org/html/rfc7231#section-6.5.5
            if ($this->compareUri($pathRegex, $resolvedPath, $uriVars)) {
                $foundRoute = $this->matchRoute($route, $requestUri, $requestMethod, $matchDomain);

                return $this->mergeRouteArguments($foundRoute, \array_replace($pathVars, $uriVars));
            }
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
     * @param Route                         $route
     * @param array<int|string,null|string> $arguments
     *
     * @return Route
     */
    protected function mergeRouteArguments(Route $route, array $arguments): Route
    {
        foreach ($arguments as $key => $value) {
            $route->argument($key, $value);
        }

        return $route;
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
            $hostScheme   = isset($schemes['https']) ? 'https' : \end($schemesKeys);
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

        return $this->mergeRouteArguments($route, \array_replace($hostVars, $hostParameters));
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
        $replaces = [];

        foreach ($values as $key => $value) {
            $replaces["<{$key}>"] = (\is_array($value) || $value instanceof \Closure) ? '' : $value;
        }

        return \strtr($string, $replaces + self::URI_FIXERS);
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
