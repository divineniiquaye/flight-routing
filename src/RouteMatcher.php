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

    /** @var Matchers\SimpleRouteCompiler */
    private $compiler;

    public function __construct(\Iterator $collection, ?RouteCompilerInterface $compiler = null, string $cacheFile = null)
    {
        $this->compiler = $compiler ?? new Matchers\SimpleRouteCompiler();
        $this->routes = $collection;

        if (!empty($cacheFile)) {
            $this->dumper = \file_exists($cacheFile)
                ? require $cacheFile
                : new Matchers\SimpleRouteDumper($this->routes, $this->compiler, $cacheFile);
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

        $resolvedPath = \substr($resolvedPath, 0, ('/' !== $resolvedPath && '/' === $resolvedPath[-1]) ? -1 : null);
        [$matchedRoute, $matchedDomains, $variables] = $this->matchRoute($resolvedPath = \rawurldecode($resolvedPath));

        if ($matchedRoute instanceof Route) {
            $schemes = $matchedRoute->get('schemes');

            if (null === $matchedRoute->get('methods')[$request->getMethod()] ?? null) {
                throw new MethodNotAllowedException(\array_keys($matchedRoute->get('methods')), $requestUri->getPath(), $request->getMethod());
            }

            if ([] !== $schemes && !isset($schemes[$requestUri->getScheme()])) {
                throw new UriHandlerException(\sprintf('Unfortunately current scheme "%s" is not allowed on requested uri [%s]', $requestUri->getScheme(), $resolvedPath), 400);
            }

            if ([] !== $matchedDomains) {
                $hostVars = [];

                if (!$this->compareDomain($matchedDomains, $requestUri, $hostVars)) {
                    throw new UriHandlerException(\sprintf('Unfortunately current domain is not allowed on requested uri [%s]', $resolvedPath), 400);
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
        static $uriRoute;

        if (\is_array($this->dumper)) {
            [$route, $compiledRoute] = $this->dumper[2][$routeName] ?? [null, null];
            $route = $this->dumper[3][$route] ?? null;

            if (null !== $route) {
                $uriRoute = $this->buildPath($route, $compiledRoute, $parameters);
            }
        } else {
            foreach ($this->routes as $route) {
                if ($routeName === $route->get('name')) {
                    $uriRoute = $this->buildPath($route, $this->compiler->compile($route, true), $parameters);

                    break;
                }
            }
        }

        if (null !== $uriRoute) {
            return $this->resolveUri($uriRoute, $queryParams);
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

        if ([] !== $schemes = $route->get('schemes')) {
            $schemes = [isset($_SERVER['HTTPS']) ? 'https' : 'http' => true];

            if ('' === $host) {
                $host = $_SERVER['HTTP_HOST'] ?? '';
            }
        }

        if ('' !== $host) {
            // If we have s secured scheme, it should be served
            $hostScheme = isset($schemes['https']) ? 'https' : (\array_key_last($schemes) ?? 'http');
            $path = "{$hostScheme}://" . \trim($this->interpolate($host, $parameters), '.');
        }

        return $path .= $this->interpolate($compiledRoute->getRegex(), $parameters);
    }

    protected function matchRoute(string $resolvedPath): array
    {
        if (\is_array($dumper = $this->dumper)) {
            $matched = $dumper[0][$resolvedPath] ?? $this->getCompiledRoute($resolvedPath, $dumper[1]);

            if (null !== $matched) {
                $matched[0] = $dumper[3][$matched[0]];
            }

            return $matched ?? [null, [], []];
        }

        if ($dumper instanceof Matchers\SimpleRouteDumper) {
            $dumper->dump();
        }

        foreach ($this->routes as $route) {
            $compiledRoute = $this->compiler->compile($route);
            $uriVars = [];

            // https://tools.ietf.org/html/rfc7231#section-6.5.5
            if (1 !== \preg_match($compiledRoute->getRegex(), $resolvedPath, $uriVars)) {
                continue;
            }

            return [$route, $compiledRoute->getHostsRegex(), $uriVars + $compiledRoute->getVariables()];
        }

        return [null, [], []];
    }

    protected function getCompiledRoute(string $resolvedPath, array $regexpList): ?array
    {
        [$regexpList, $parameters] = $regexpList;

        // https://tools.ietf.org/html/rfc7231#section-6.5.5
        if (1 === \preg_match($regexpList, $resolvedPath, $urlVariables)) {
            $routeId = $urlVariables['MARK'];
            unset($urlVariables['MARK']);
            \array_shift($urlVariables); // Remove index 0 from matched $keys

            [$matchedDomains, $variables, $varKeys] = $parameters[$routeId];

            foreach ($varKeys as $index => $key) {
                $variables[$key] = $urlVariables[$index] ?? null;
            }

            return [$routeId, $matchedDomains, $variables];
        }

        return null;
    }

    /**
     * @param array<int|string,int|string> $queryParams
     */
    private function resolveUri(string $createdUri, array $queryParams): string
    {
        $prefix = '.'; // Append missing "." at the beginning of the $uri.

        // Making routing on sub-folders easier
        if (0 !== \strpos($createdUri, '/')) {
            $prefix .= '/';
        }

        // Incase query is added to uri.
        if (!empty($queryParams)) {
            $createdUri .= '?' . \http_build_query($queryParams);
        }

        if (false === \strpos($createdUri, '://')) {
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

    /**
     * Check if given request domain matches given route domain.
     *
     * @param string[]                 $routeDomains
     * @param array<int|string,string> $parameters
     */
    protected function compareDomain(array $routeDomains, UriInterface $requestUri, array &$parameters): bool
    {
        $hostAndPort = $requestUri->getHost();

        // Added port to host for matching ...
        if (null !== $requestUri->getPort()) {
            $hostAndPort .= ':' . $requestUri->getPort();
        }

        foreach ($routeDomains as $routeDomain) {
            if (1 === \preg_match($routeDomain, $hostAndPort, $parameters)) {
                return true;
            }
        }

        return false;
    }
}
