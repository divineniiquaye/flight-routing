<?php

declare(strict_types=1);

/*
 * This file is part of Flight Routing.
 *
 * PHP version 7.2 and above required
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 Biurad Group (https://biurad.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flight\Routing;

use Closure;
use Flight\Routing\Concerns\HttpMethods;
use Flight\Routing\Exceptions\DuplicateRouteException;
use Flight\Routing\Exceptions\UrlGenerationException;
use Flight\Routing\Interfaces\CallableResolverInterface;
use Flight\Routing\Interfaces\RouteCollectorInterface;
use Flight\Routing\Interfaces\RouteGroupInterface;
use Flight\Routing\Interfaces\RouteInterface;
use Flight\Routing\Interfaces\RouterInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use RuntimeException;

/**
 * Aggregate routes for the router.
 *
 * This class provides all(*) methods for creating path+HTTP method-based routes and
 * injecting them into the router:
 *
 * - get
 * - post
 * - put
 * - patch
 * - delete
 * - any
 * - map
 *
 * A general `map()` method allows specifying multiple request methods and/or
 * arbitrary request methods when creating a path-based route.
 *
 * Internally, the class performs some checks for duplicate routes when
 * attaching via one of the exposed methods, and will raise an exception when a
 * collision occurs.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class RouteCollector implements Interfaces\RouteCollectorInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var Middlewares\MiddlewareDisptcher
     */
    protected $middlewareDispatcher;

    /**
     * @var CallableResolverInterface
     */
    protected $callableResolver;

    /**
     * @var null|ContainerInterface
     */
    protected $container;

    /**
     * @var ResponseFactoryInterface
     */
    protected $responseFactory;

    /**
     * The current route being used.
     *
     * @var RouteInterface
     */
    protected $currentRoute;

    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var array|RouteInterface[]
     */
    protected $nameList = [];

    /**
     * Route groups.
     *
     * @var RouteGroup
     */
    protected $routeGroup;

    /**
     * Route Default Namespace.
     *
     * @var string
     */
    protected $namespace;

    /**
     * Route Group Options.
     *
     * @var array
     */
    protected $groupOptions = [];

    /**
     * Add this to keep the HTTP method when redirecting.
     *
     * @var bool
     */
    protected $keepRequestMethod = false;

    /**
     * @param ResponseFactoryInterface  $responseFactory
     * @param null|RouterInterface      $router
     * @param CallableResolverInterface $callableResolver
     * @param null|ContainerInterface   $container
     */
    public function __construct(
        ResponseFactoryInterface $responseFactory,
        RouterInterface $router = null,
        CallableResolverInterface $callableResolver = null,
        ContainerInterface $container = null
    ) {
        $this->responseFactory = $responseFactory;
        $this->router          = $router ?? new Services\DefaultFlightRouter();

        $this->container            = $container;
        $this->callableResolver     = $callableResolver ?? new Concerns\CallableResolver($container);
        $this->middlewareDispatcher = new Middlewares\MiddlewareDisptcher([], $this->container);
    }

    /**
     * @return array
     */
    public function __debugInfo()
    {
        return [
            'routes'    => $this->getRoutes(),
            'current'   => $this->currentRoute,
            'counts'    => $this->getRouter()->count(),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function addParameters(array $parameters, int $type = self::TYPE_REQUIREMENT): RouteCollectorInterface
    {
        foreach ($parameters as $key => $regex) {
            if (self::TYPE_DEFAULT === $type) {
                $this->groupOptions[RouteGroupInterface::DEFAULTS] = [$key => $regex];

                continue;
            }

            $this->groupOptions[RouteGroupInterface::REQUIREMENTS] = [$key => $regex];
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getRoutes(): array
    {
        return \iterator_to_array($this->getRouter());
    }

    /**
     * {@inheritdoc}
     */
    public function keepRequestMethod(bool $status = false): RouteCollectorInterface
    {
        $this->keepRequestMethod = $status;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getNamedRoute(string $name): RouteInterface
    {
        foreach ($this->router as $route) {
            if ($name === $route->getName()) {
                return $route;
            }
        }

        throw new RuntimeException('Named route does not exist for name: ' . $name);
    }

    /**
     * {@inheritdoc}
     */
    public function group(array $attributes, $callable): RouteGroupInterface
    {
        // Backup current properties
        $oldGroupOption = $this->groupOptions;
        $oldGroup       = $this->routeGroup;

        // Register the Route Grouping
        $routeCollectorProxy = new RouterProxy($this->responseFactory, $this->router, $this);
        $routeGroup          = new RouteGroup($attributes, $callable, $this->callableResolver, $routeCollectorProxy);

        // Add goups to RouteCollection
        $this->routeGroup   = $routeGroup->mergeBackupAttributes($oldGroup);
        $this->groupOptions = $this->resolveGlobals($routeGroup->getOptions(), $oldGroupOption);

        // Returns routes on closure, file or on callble
        $routeGroup->collectRoutes();

        // Restore properties
        $this->groupOptions = $oldGroupOption;
        $this->routeGroup   = $oldGroup;

        return $routeGroup;
    }

    /**
     * Register a new GET route with the router.
     *
     * @param string                    $uri
     * @param null|array|Closure|string $action
     *
     * @return RouteInterface
     */
    public function get($uri, $action = null): RouteInterface
    {
        return $this->map([HttpMethods::METHOD_GET, HttpMethods::METHOD_HEAD], $uri, $action);
    }

    /**
     * Register a new POST route with the router.
     *
     * @param string                    $uri
     * @param null|array|Closure|string $action
     *
     * @return RouteInterface
     */
    public function post($uri, $action = null): RouteInterface
    {
        return $this->map([HttpMethods::METHOD_POST], $uri, $action);
    }

    /**
     * Register a new PUT route with the router.
     *
     * @param string                    $uri
     * @param null|array|Closure|string $action
     *
     * @return RouteInterface
     */
    public function put($uri, $action = null): RouteInterface
    {
        return $this->map([HttpMethods::METHOD_PUT], $uri, $action);
    }

    /**
     * Register a new PATCH route with the router.
     *
     * @param string                    $uri
     * @param null|array|Closure|string $action
     *
     * @return RouteInterface
     */
    public function patch($uri, $action = null): RouteInterface
    {
        return $this->map([HttpMethods::METHOD_PATCH], $uri, $action);
    }

    /**
     * Register a new DELETE route with the router.
     *
     * @param string                    $uri
     * @param null|array|Closure|string $action
     *
     * @return RouteInterface
     */
    public function delete($uri, $action = null): RouteInterface
    {
        return $this->map([HttpMethods::METHOD_DELETE], $uri, $action);
    }

    /**
     * Register a new OPTIONS route with the router.
     *
     * @param string                    $uri
     * @param null|array|Closure|string $action
     *
     * @return RouteInterface
     */
    public function options($uri, $action = null): RouteInterface
    {
        return $this->map([HttpMethods::METHOD_OPTIONS], $uri, $action);
    }

    /**
     * Register a new route responding to all verbs.
     *
     * @param string                    $uri
     * @param null|array|Closure|string $action
     *
     * @return RouteInterface
     */
    public function any($uri, $action = null): RouteInterface
    {
        return $this->map([HttpMethods::HTTP_METHODS_STANDARD], $uri, $action);
    }

    /**
     * {@inheritdoc}
     */
    public function map(array $methods, string $pattern, $handler = null): RouteInterface
    {
        return $this->addRoute(\array_map('strtoupper', $methods), $pattern, $handler);
    }

    /**
     * {@inheritdoc}
     */
    public function setRoute(RouteInterface $route): void
    {
        \assert($route instanceof Route);

        // Configure route with needed dependencies.
        $defaults = [
            RouteGroup::NAMESPACE       => $this->namespace,
            RouteGroup::DEFAULTS        => $this->groupOptions[RouteGroupInterface::DEFAULTS] ?? null,
            RouteGroup::REQUIREMENTS    => $this->groupOptions[RouteGroupInterface::REQUIREMENTS] ?? null,
        ];

        $route->fromArray($defaults);
        $this->router->addRoute($route);
    }

    /**
     * {@inheritdoc}
     */
    public function addMiddlewares($middleware = []): RouteCollectorInterface
    {
        $this->middlewareDispatcher->add($middleware);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function routeMiddlewares($middlewares = []): RouteCollectorInterface
    {
        $this->middlewareDispatcher->add(['routing' => $middlewares]);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getMiddlewaresStack(): array
    {
        return $this->middlewareDispatcher->getMiddlewareStack();
    }

    /**
     * {@inheritdoc}
     */
    public function setNamespace(string $rootNamespace): RouteCollectorInterface
    {
        $this->namespace = $rootNamespace;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function generateUri(string $routeName, array $parameters = [], array $queryParams = []): ?string
    {
        if (isset($this->nameList[$routeName]) === false) {
            throw new UrlGenerationException(
                \sprintf(
                    'Unable to generate a URL for the named route "%s" as such route does not exist.',
                    $routeName
                ),
                404
            );
        }

        $prefix = '.'; // Append missing "." at the beginning of the $uri.

        // Making routing on sub-folders easier
        if (\strpos($uri = $this->router->generateUri($this->getNamedRoute($routeName), $parameters), '/') !== 0) {
            $prefix .= '/';
        }

        // Incase query is added to uri.
        if (!empty($queryParams)) {
            $uri .= '?' . \http_build_query($queryParams);
        }

        return \rtrim(\strpos($uri, '://') !== false ? $uri : $prefix . $uri, '/');
    }

    /**
     * {@inheritdoc}
     */
    public function currentRoute(): ?RouteInterface
    {
        return $this->currentRoute;
    }

    /**
     * {@inheritdoc}
     */
    public function getRouter(): RouterInterface
    {
        return $this->router;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Add Route Names RouteCollector.
        $routes = $this->getRoutes();
        \array_walk($routes, [$this, 'addLookupRoute']);

        $routingResults     = $this->router->match($request);
        $this->currentRoute = $route = $routingResults->getMatchedRoute();
        $routingResults->bindTo($request, $this->keepRequestMethod, $this->callableResolver, $this->responseFactory);

        // Get all available middlewares
        if (\count($middlewares = $this->getMiddlewares($route ? $route->getMiddlewares() : [])) > 0) {
            $middleware = $this->middlewareDispatcher->pipeline($middlewares);

            try {
                $requestHandler = $this->middlewareDispatcher->addHandler($routingResults);
            } finally {
                // This middleware is in the priority map; but, this is the first middleware we have
                // encountered from the map thus far. We'll save its current index plus its index
                // from the priority map so we can compare against them on the next iterations.
                return $middleware->process($request, $requestHandler);
            }
        }

        return $routingResults->handle($request);
    }

    /**
     * Create a new Route object.
     *
     * @param array|string $methods
     * @param string       $uri
     * @param mixed        $action
     *
     * @return Route
     */
    protected function newRoute($methods, $uri, $action): Route
    {
        return new Route((array) $methods, $uri, $action, $this->routeGroup);
    }

    /**
     * Resolving patterns and defaults to group.
     *
     * @param array $groupOptions
     * @param array $previousOptions
     *
     * @return array
     */
    protected function resolveGlobals(array $groupOptions, array $previousOptions): array
    {
        $groupOptions[RouteGroup::REQUIREMENTS] = \array_replace(
            $this->groupOptions[RouteGroup::REQUIREMENTS] ?? [],
            $previousOptions[RouteGroup::REQUIREMENTS] ?? [],
            $groupOptions[RouteGroup::REQUIREMENTS] ?? []
        );

        $groupOptions[RouteGroup::DEFAULTS] = \array_replace(
            $this->groupOptions[RouteGroup::DEFAULTS] ?? [],
            $previousOptions[RouteGroup::DEFAULTS] ?? [],
            $groupOptions[RouteGroup::DEFAULTS] ?? []
        );

        return \array_intersect_key(
            \array_filter($groupOptions),
            \array_flip([RouteGroup::DEFAULTS, RouteGroup::REQUIREMENTS])
        );
    }

    /**
     * Merge route middlewares with Router Middlewares.
     *
     * @param array $middlewares
     *
     * @return array
     */
    protected function getMiddlewares(array $middlewares): array
    {
        return \array_filter(
            \array_replace($middlewares, $this->getMiddlewaresStack()),
            function ($middleware) {
                return !\in_array($middleware, ['off', 'disable'], true);
            }
        );
    }

    /**
     * Create a new route instance.
     *
     * @param array|string $methods
     * @param string       $uri
     * @param mixed        $action
     *
     * @return Route
     */
    private function createRoute($methods, $uri, $action): Route
    {
        $route = $this->newRoute($methods, $uri, $action);

        // Set the defualts for group routing.
        $defaults = [
            RouteGroup::NAMESPACE       => $this->namespace,
            RouteGroup::DEFAULTS        => $this->groupOptions[RouteGroupInterface::DEFAULTS] ?? null,
            RouteGroup::REQUIREMENTS    => $this->groupOptions[RouteGroupInterface::REQUIREMENTS] ?? null,
        ];

        $route->fromArray($defaults);

        return $route;
    }

    /**
     * Add a route.
     *
     * Accepts a combination of a path, controller, domain and requesthandler,
     * and optionally the HTTP methods allowed.
     *
     * @param array|array               $methods HTTP method to accept
     * @param null|string               $uri     the uri of the route
     * @param null|array|Closure|string $action  a requesthandler or controller
     *
     * @throws RuntimeException when called after match() have been called
     *
     * @return Route
     */
    private function addRoute($methods, $uri, $action): Route
    {
        $this->checkForDuplicateRoute($uri, $methods);

        // Add Route to a parsing Router.
        $this->router->addRoute($route = $this->createRoute($methods, $uri, $action));

        return $route;
    }

    /**
     * Lookup a route via the route's unique identifier.
     *
     * @param RouteInterface $route
     */
    private function addLookupRoute(RouteInterface $route): void
    {
        if (null === $name = $route->getName()) {
            return;
        }

        $this->nameList[$name] = $route;
    }

    /**
     * Determine if the route is duplicated in the current list.
     *
     * Checks if a route with the same path exists already in the list;
     * if so, and it responds to any of the $methods indicated, raises
     * a DuplicateRouteException indicating a duplicate route.
     *
     * @throws DuplicateRouteException on duplicate route detection
     */
    private function checkForDuplicateRoute(string $path, array $methods): void
    {
        $allowed        = [];
        $matches        = \array_filter($this->getRoutes(), function (Route $route) use ($path, $methods, $allowed) {
            if ($path === $route->getPath()) {
                foreach ($methods as $method) {
                    if (\in_array($method, $route->getMethods(), true)) {
                        $allowed[] = $method;

                        return true;
                    }
                }

                return false;
            }

            return false;
        });

        if (!empty($matches)) {
            throw new DuplicateRouteException(
                \sprintf(
                    'Duplicate route detected; path "%s" answering to methods [%s]',
                    \reset($matches)->getPath(),
                    \implode(',', $allowed ?: ['(any)'])
                )
            );
        }
    }
}
