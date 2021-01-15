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

use Closure;
use Flight\Routing\Exceptions\UriHandlerException;
use Flight\Routing\Interfaces\RouteInterface;
use Flight\Routing\Interfaces\RouteListInterface;
use Flight\Routing\Interfaces\RouteMatcherInterface;
use Flight\Routing\RouteList;
use Flight\Routing\Router;
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

    public function __construct()
    {
        $this->compiler = new SimpleRouteCompiler();
    }

    /**
     * {@inheritdoc}
     */
    public function matchRoutes(Router $router, ServerRequestInterface $request): ?RouteInterface
    {
        list($staticRoutes, $dynamicRoutes) = $this->getCompiledRoutes($router);

        $requestUri    = $request->getUri();
        $requestMethod = $request->getMethod();
        $resolvedPath  = $this->resolvePath($request, $requestUri->getPath());

        // Checks if $route is a static type
        if (isset($staticRoutes[$resolvedPath])) {
            return $this->matchRoute($staticRoutes[$resolvedPath], $requestUri, $requestMethod);
        }

        /** @var SimpleRouteCompiler $route */
        foreach ($dynamicRoutes as $name => $route) {
            $uriParameters = [];

            // https://tools.ietf.org/html/rfc7231#section-6.5.5
            if ($this->compareUri($route->getRegex(), $resolvedPath, $uriParameters)) {
                $requestRoutes = [$router->getRoute($name), $route];
                $foundRoute    = $this->matchRoute($requestRoutes, $requestUri, $requestMethod);

                return $foundRoute->setArguments(
                    \array_filter(
                        \array_replace($route->getPathVariables(), $uriParameters),
                        'is_string',
                        \ARRAY_FILTER_USE_KEY
                    )
                );
            }
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function buildPath(RouteInterface $route, array $substitutions): string
    {
        $compiledRoute = $this->compiler->compile($route);

        $parameters = \array_merge(
            $compiledRoute->getVariables(),
            $route->getDefaults(),
            $this->fetchOptions($substitutions, \array_keys($compiledRoute->getVariables()))
        );

        $path = '';

        //Uri without empty blocks (pretty stupid implementation)
        if (null !== $hostRegex = $this->compiler->getRegexTemplate()) {
            $schemes = $route->getSchemes();

            // If we have s secured scheme, it should be served
            $hostScheme = \in_array('https', $schemes, true) ? 'https' : \end($schemes);

            $path = \sprintf('%s://%s', $hostScheme, \trim($this->interpolate($hostRegex, $parameters), '.'));
        }

        return $path .= $this->interpolate($this->compiler->getRegexTemplate(false), $parameters);
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
    public function warmCompiler(RouteListInterface $routes)
    {
        $staticRoutes = $dynamicRoutes = [];

        foreach ($routes->getRoutes() as $route) {
            $compiledRoute = clone $this->compiler->compile($route);

            if (empty($compiledRoute->getPathVariables())) {
                $host = empty($compiledRoute->getHostVariables()) ? $route->getDomain() : '';
                $url  = \rtrim($route->getPath(), '/') ?: '/';

                $staticRoutes[$url] = '' === $host ? $route : [$route, $compiledRoute];

                continue;
            }

            $dynamicRoutes[$route->getName()] = $compiledRoute;
        }

        return [$staticRoutes, $dynamicRoutes];
    }

    /**
     * @param array<mixed>|RouteInterface $route
     * @param UriInterface                $requestUri
     * @param string                      $method
     *
     * @return RouteInterface
     */
    private function matchRoute($route, UriInterface $requestUri, string $method): RouteInterface
    {
        $hostParameters = [];

        if (\is_array($route)) {
            list($route, $compiledRoute) = $route;

            if (!$this->compareDomain($compiledRoute->getHostRegex(), $requestUri->getHost(), $hostParameters)) {
                throw new UriHandlerException(
                    \sprintf(
                        'Unfortunately current domain "%s" is not allowed on requested uri [%s]',
                        $requestUri->getHost(),
                        $requestUri->getPath()
                    ),
                    400
                );
            }

            $route->setArguments(\array_replace($compiledRoute->getHostVariables(), $hostParameters));
        }

        $this->assertRoute($route, $requestUri, $method);

        return $route;
    }

    /**
     * @param Router $router
     *
     * @return mixed[]
     */
    private function getCompiledRoutes(Router $router): array
    {
        if (!empty($compiledRoutes = $router->getCompiledRoutes())) {
            return $compiledRoutes;
        }

        $collection = new RouteList();
        $collection->addForeach(...$router->getRoutes());

        return $this->warmCompiler(clone $collection);
    }

    /**
     * Interpolate string with given values.
     *
     * @param null|string             $string
     * @param array<int|string,mixed> $values
     *
     * @return string
     */
    private function interpolate(?string $string, array $values): string
    {
        $replaces = [];

        foreach ($values as $key => $value) {
            $replaces["<{$key}>"] = (\is_array($value) || $value instanceof Closure) ? '' : $value;
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
     * @param string                 $requestPath
     *
     * @return string
     */
    private function resolvePath(ServerRequestInterface $request, string $requestPath): string
    {
        if (\strlen($basePath = \dirname($request->getServerParams()['SCRIPT_NAME'] ?? '')) > 1) {
            $requestPath = \substr($requestPath, \strlen($basePath)) ?: $requestPath;
        }

        if (\strlen($requestPath) > 1) {
            $requestPath = \rtrim($requestPath, '/');
        }

        return \rawurldecode($requestPath);
    }
}
