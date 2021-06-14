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
    private const URI_FIXERS = [
        '[]' => '',
        '[/]' => '',
        '[' => '',
        ']' => '',
        '://' => '://',
        '//' => '/',
    ];

    /** @var \Iterator<int,Route>|\Iterator<int,array> */
    protected $routes = [];

    /** @var Matchers\SimpleRouteDumper|array|null */
    private $dumper = null;

    /** @var RouteCompilerInterface */
    private $compiler;

    public function __construct(\Iterator $collection, ?RouteCompilerInterface $compiler = null, string $cacheFile = null)
    {
        $this->compiler = $compiler ?? new Matchers\SimpleRouteCompiler();
        $this->routes = $collection;

        if (!empty($cacheFile)) {
            if (\file_exists($cacheFile)) {
                $cachedRoutes = require $cacheFile;

                $this->routes = new \ArrayIterator($cachedRoutes[3]);
                unset($cachedRoutes[3]);
            }

            $this->dumper = $cachedRoutes ?? new Matchers\SimpleRouteDumper($cacheFile);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function match(ServerRequestInterface $request): ?Route
    {
        $requestUri = $request->getUri();

        // Resolve request path to match sub-directory or /index.php/path
        if (empty($resolvedPath = $request->getServerParams()['PATH_INFO'] ?? '')) {
            $resolvedPath = $requestUri->getPath();
        }

        if ('/' !== $resolvedPath && isset(Route::URL_PREFIX_SLASHES[$resolvedPath[-1]])) {
            $resolvedPath = \substr($resolvedPath, 0, -1);
        }

        [$matchedRoute, $matchedDomains, $variables] = $this->matchRoute($resolvedPath = \rawurldecode($resolvedPath));

        if ($matchedRoute instanceof Route) {
            $schemes = $matchedRoute->get('schemes');

            if (!\array_key_exists($request->getMethod(), $matchedRoute->get('methods'))) {
                throw new MethodNotAllowedException(\array_keys($matchedRoute->get('methods')), $requestUri->getPath(), $request->getMethod());
            }

            if (!empty($schemes) && !\array_key_exists($requestUri->getScheme(), $schemes)) {
                throw new UriHandlerException(\sprintf('Unfortunately current scheme "%s" is not allowed on requested uri [%s]', $requestUri->getScheme(), $resolvedPath), 400);
            }

            if (!empty($matchedDomains)) {
                if (null === $hostVars = $this->compareDomain($matchedDomains, $requestUri)) {
                    throw new UriHandlerException(\sprintf('Unfortunately current domain "%s" is not allowed on requested uri [%s]', $requestUri->getHost(), $resolvedPath), 400);
                }

                $variables = \array_replace($variables, $hostVars);
            }

            foreach ($variables as $key => $value) {
                if (\is_int($key)) {
                    continue;
                }

                $matchedRoute->argument($key, $value);
            }
        }

        return $matchedRoute;
    }

    /**
     * {@inheritdoc}
     *
     * @return string of fully qualified URL for named route
     */
    public function generateUri(string $routeName, array $parameters = [], array $queryParams = []): string
    {
        foreach ($this->routes as $route) {
            if (!$route instanceof Route) {
                $route = Route::__set_state($route);
            }

            if ($routeName === $route->get('name')) {
                $compiledRoute = $this->isCompiled() ? \unserialize($this->dumper[2][$routeName]) : $this->compiler->compile($route, true);
                $uriRoute = $this->buildPath($route, $compiledRoute, $parameters);

                // Incase query is added to uri.
                if ([] !== $queryParams) {
                    $uriRoute .= '?' . \http_build_query($queryParams);
                }

                if (!\str_contains($uriRoute, '://')) {
                    $prefix = '.'; // Append missing "." at the beginning of the $uri.

                    if ('/' !== @$uriRoute[0]) {
                        $prefix .= '/';
                    }

                    $uriRoute = $prefix . $uriRoute;
                }

                return $uriRoute;
            }
        }

        throw new UrlGenerationException(\sprintf('Unable to generate a URL for the named route "%s" as such route does not exist.', $routeName), 404);
    }

    public function getCompiler(): RouteCompilerInterface
    {
        return $this->compiler;
    }

    /**
     * Return true if routes are compiled.
     */
    public function isCompiled(): bool
    {
        return \is_array($this->dumper);
    }

    /**
     * This method is used to build uri, this can be overwritten.
     *
     * @param array<int|string,int|string> $parameters
     */
    protected function buildPath(Route $route, CompiledRoute $compiledRoute, array $parameters): string
    {
        $path = $host = '';

        // Fetch and merge all possible parameters + variables keys + route defaults ...
        $parameters = $this->fetchOptions($parameters, \array_keys($compiledRoute->getVariables()));
        $parameters = $parameters + $route->get('defaults') + $compiledRoute->getVariables();

        if (1 === \count($hostRegexs = $compiledRoute->getHostsRegex())) {
            $host = $hostRegexs[0];
        }

        if (!empty($schemes = $route->get('schemes'))) {
            $schemes = [isset($_SERVER['HTTPS']) ? 'https' : 'http' => true];

            if (empty($host)) {
                $host = $_SERVER['HTTP_HOST'] ?? '';
            }
        }

        if (!empty($host)) {
            // If we have s secured scheme, it should be served
            $hostScheme = isset($schemes['https']) ? 'https' : (\array_key_last($schemes) ?? 'http');
            $path = "{$hostScheme}://" . \trim($this->interpolate($host, $parameters), '.');
        }

        return $path .= $this->interpolate($compiledRoute->getRegex(), $parameters);
    }

    /**
     * Match Route based on HTTP request path.
     */
    protected function matchRoute(string $resolvedPath): array
    {
        if (\is_array($dumper = $this->dumper)) {
            [$staticRoutes, $regexpList] = $dumper;

            if ([null, [], []] !== $matchedRoute = $staticRoutes[$resolvedPath] ?? [null, [], []]) {
                $route = $this->routes[$matchedRoute[0]];
                $matchedRoute[0] = $route instanceof Route ? $route : Route::__set_state($route);

                return $matchedRoute;
            }

            if (1 === \preg_match($regexpList[0], $resolvedPath, $urlVariables)) {
                $route = $this->routes[$routeId = $urlVariables['MARK']];
                [$matchedDomains, $variables, $varKeys] = $regexpList[1][$routeId];

                foreach ($varKeys as $index => $key) {
                    $variables[$key] = $urlVariables[$index] ?? null;
                }

                return [$route instanceof Route ? $route : Route::__set_state($route), $matchedDomains, $variables];
            }

            return $matchedRoute;
        }

        foreach ($this->routes as $route) {
            $compiledRoute = $this->compiler->compile($route);

            // https://tools.ietf.org/html/rfc7231#section-6.5.5
            if ($resolvedPath === $compiledRoute->getStatic() || 1 === \preg_match($compiledRoute->getRegex(), $resolvedPath, $uriVars)) {
                try {
                    return [$route, $compiledRoute->getHostsRegex(), ($uriVars ?? []) + $compiledRoute->getVariables()];
                } finally {
                    if ($dumper instanceof Matchers\SimpleRouteDumper) {
                        $dumper->dump($this->routes, $this->compiler);
                    }
                }
            }
        }

        return [null, [], []];
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

    /**
     * Check if given request domain matches given route domain.
     *
     * @param string|string[] $routeDomains
     *
     * @return array<int|string,mixed>|null
     */
    protected function compareDomain($routeDomains, UriInterface $requestUri): ?array
    {
        $hostAndPort = $requestUri->getHost();

        // Added port to host for matching ...
        if (null !== $requestUri->getPort()) {
            $hostAndPort .= ':' . $requestUri->getPort();
        }

        if (\is_string($routeDomains)) {
            return 1 === \preg_match($routeDomains, $hostAndPort, $parameters) ? $parameters : null;
        }

        foreach ($routeDomains as $routeDomain) {
            if (1 === \preg_match($routeDomain, $hostAndPort, $parameters)) {
                return $parameters;
            }
        }

        return null;
    }
}
