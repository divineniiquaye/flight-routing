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

use Flight\Routing\Exceptions\UrlGenerationException;
use Flight\Routing\Interfaces\{RouteCompilerInterface, RouteMatcherInterface};
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

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
        '/..' => '/%2E%2E',
        '/.' => '/%2E',
    ];

    /** @var iterable<int,Route> */
    protected $routes = [];

    /** @var RouteCompilerInterface */
    private $compiler;

    public function __construct(iterable $collection, ?RouteCompilerInterface $compiler = null)
    {
        $this->compiler = $compiler ?? new Matchers\SimpleRouteCompiler();
        $this->routes = $collection;
    }

    /**
     * {@inheritdoc}
     */
    public function matchRequest(ServerRequestInterface $request): ?Route
    {
        $requestUri = $request->getUri();

        // Resolve request path to match sub-directory or /index.php/path
        if (!empty($pathInfo = $request->getServerParams()['PATH_INFO'] ?? '')) {
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

        if ('/' !== $requestPath && isset(Route::URL_PREFIX_SLASHES[$requestPath[-1]])) {
            $requestPath = \substr($requestPath, 0, -1);
        }

        return array_reduce((array) $this->routes, function (?Route $carry, Route $item) use ($method, $requestPath, $uri) {
            if ($carry instanceof Route) {
                return $carry;
            }

            $compiledRoute = $this->compiler->compile($item);

            if (2 === strpos($routeRegex = (string) $compiledRoute, '\/')) {
                $port = $uri->getPort();
                $requestPathWithHost = '//' . $uri->getHost() . (null !== $port ? ':' . $port : '') . $requestPath;
            }

            // Match static or dynamic route ...
            if (1 === \preg_match($routeRegex, $requestPathWithHost ?? $requestPath, $uriVars)) {
                return $carry = $item->match($method, $uri, $uriVars + $compiledRoute->getVariables());
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function generateUri(string $routeName, array $parameters = []): GeneratedUri
    {
        foreach ($this->routes as $route) {
            if ($routeName === $route->get('name')) {
                $compiledRoute = $this->compiler->compile($route, true);

                return $this->buildPath($route, $compiledRoute, $parameters);
            }
        }

        throw new UrlGenerationException(\sprintf('Unable to generate a URL for the named route "%s" as such route does not exist.', $routeName), 404);
    }

    public function getCompiler(): RouteCompilerInterface
    {
        return $this->compiler;
    }

    /**
     * This method is used to build uri, this can be overwritten.
     *
     * @param array<int|string,int|string> $parameters
     */
    protected function buildPath(Route $route, CompiledRoute $compiledRoute, array $parameters): GeneratedUri
    {
        $defaults = $route->get('defaults');
        unset($defaults['_arguments']);

        $pathRegex = $compiledRoute->getPath() ?? $compiledRoute->getPathRegex();
        $pathVariables = $this->fetchOptions($pathRegex, $parameters, $defaults, $variables = $compiledRoute->getVariables());

        if (!empty($hostRegex = $compiledRoute->getHostsRegex())) {
            $hostRegex = \is_string($hostRegex) ? $hostRegex : \end($hostRegex);
        }

        $createUri = new GeneratedUri($this->interpolate($pathRegex, $pathVariables));

        if (!empty($schemes = $route->get('schemes'))) {
            $createUri->withScheme(\in_array('https', $schemes, true) ? 'https' : \end($schemes));

            if (empty($hostRegex)) {
                $createUri->withHost($_SERVER['HTTP_HOST'] ?? '');
            }
        }

        if (!empty($hostRegex)) {
            $createUri->withHost($this->interpolate($hostRegex, $this->fetchOptions($hostRegex, $parameters, $defaults, $variables)));
        }

        return $createUri;
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
     *
     * @return array<int|string,mixed>
     */
    private function fetchOptions(string $uriRoute, array $parameters, array $defaults, array $allowed): array
    {
        \preg_match_all('#\[\<(\w+).*?\>\]#', $uriRoute, $optionalVars, \PREG_UNMATCHED_AS_NULL);

        if (isset($optionalVars[1])) {
            foreach ($optionalVars[1] as $optional) {
                $defaults[$optional] = null;
            }
        }

        // Fetch and merge all possible parameters + route defaults ...
        $parameters += $defaults;

        // all params must be given
        if ($diff = \array_diff_key($allowed, $parameters)) {
            throw new UrlGenerationException(\sprintf('Some mandatory parameters are missing ("%s") to generate a URL for route path "%s".', \implode('", "', \array_keys($diff)), $uriRoute));
        }

        return $parameters;
    }
}
