<?php

declare(strict_types=1);

/*
 * This code is under BSD 3-Clause "New" or "Revised" License.
 *
 * PHP version 7 and above required
 *
 * @category  RoutingManager
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 Biurad Group (https://biurad.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * @link      https://www.biurad.com/projects/routingmanager
 * @since     Version 0.1
 */

namespace Flight\Routing;

use function array_merge;
use function array_shift;
use function array_walk;
use Closure;
use function count;
use Flight\Routing\Concerns\HttpMethods;
use Flight\Routing\Exceptions\UrlGenerationException;
use Flight\Routing\Interfaces\CallableResolverInterface;
use Flight\Routing\Interfaces\RouteCollectorInterface;
use Flight\Routing\Interfaces\RouteGroupInterface;
use Flight\Routing\Interfaces\RouteInterface;
use Flight\Routing\Interfaces\RouterInterface;
use function http_build_query;
use function in_array;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use RuntimeException;
use SplObjectStorage;
use function sprintf;
use function str_replace;
use function strpos;

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
 * - resource
 *
 * A general `map()` method allows specifying multiple request methods and/or
 * arbitrary request methods when creating a path-based route.
 *
 * Internally, the class performs some checks for duplicate routes when
 * attaching via one of the exposed methods, and will raise an exception when a
 * collision occurs.
 */
class RouteCollector implements Interfaces\RouteCollectorInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var array|RouteInterface[]string
     */
    protected $allRoutes = [];

    /**
     * List of all routes registered directly with the application.
     *
     * @var Route[]|SplObjectStorage
     */
    private $routes;

    /**
     * @var Middlewares\MiddlewareDisptcher
     */
    protected $middlewareDispatcher;

    /**
     * @var CallableResolverInterface
     */
    protected $callableResolver;

    /**
     * @var ServerRequestInterface
     */
    protected $request;

    /**
     * @var ContainerInterface|null
     */
    protected $container;

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
     * @var RouteInterface[]|array
     */
    protected $nameList = [];

    /**
     * Route groups.
     *
     * @var RouteGroup[]
     */
    protected $routeGroups = [];

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
     * @var ResponseFactoryInterface
     */
    protected $responseFactory;

    /**
     * @param ServerRequestInterface    $request
     * @param ResponseFactoryInterface  $responseFactory
     * @param RouterInterface|null      $router
     * @param CallableResolverInterface $callableResolver
     * @param ContainerInterface|null   $container
     */
    public function __construct(
        ServerRequestInterface $request,
        ResponseFactoryInterface $responseFactory,
        RouterInterface $router = null,
        CallableResolverInterface $callableResolver = null,
        ContainerInterface $container = null
    ) {
        $this->request = $request;
        $this->responseFactory = $responseFactory;

        $this->routes = new SplObjectStorage();
        $this->router = $router ?? new Services\DefaultFlightRouter();

        $this->container = $container;
        $this->callableResolver = $callableResolver ?? new Concerns\CallableResolver($container);
        $this->middlewareDispatcher = new Middlewares\MiddlewareDisptcher([], $this->container);
    }

    /**
     * {@inheritdoc}
     */
    public function addParameters(array $parameters, int $type = self::TYPE_REQUIREMENT): RouteCollectorInterface
    {
        foreach ($parameters as $key => $regex) {
            if (self::TYPE_DEFAULT === $type) {
                $this->setGroupOption(RouteGroupInterface::DEFAULTS, [$key => $regex]);
                break;
            }

            $this->setGroupOption(RouteGroupInterface::REQUIREMENTS, [$key => $regex]);
        }

        return $this;
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
    protected function createRoute($methods, $uri, $action): Route
    {
        $route = $this->newRoute($methods, $uri, $action);

        // Set the defualts for group routing.
        $defaults = [
            'namespace' => $this->getGroupOption(RouteGroupInterface::NAMESPACE),
            'defaults'  => $this->getGroupOption(RouteGroupInterface::DEFAULTS),
            'patterns'  => $this->getGroupOption(RouteGroupInterface::REQUIREMENTS),
            'schemes'   => $this->getGroupOption(RouteGroupInterface::SCHEMES),
        ];

        $route->fromArray($defaults);

        return $route;
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
        $route = new Route(
            (array) $methods,
            $uri,
            $action,
            [$this->responseFactory, 'createResponse'],
            $this->callableResolver,
            $this->container,
            $this->routeGroups
        );

        return $route;
    }

    /**
     * Add a route.
     *
     * Accepts a combination of a path, controller, domain and requesthandler,
     * and optionally the HTTP methods allowed.
     *
     * @param array|array               $methods HTTP method to accept
     * @param string|null               $uri     the uri of the route
     * @param Closure|array|string|null $action  a requesthandler or controller
     *
     * @throws RuntimeException when called after match() have been called.
     *
     * @return Route
     */
    protected function addRoute($methods, $uri, $action): Route
    {
        $route = $this->createRoute($methods, $uri, $action);
        $domainAndUri = $route->getDomain().$route->getPath();

        // Add the given route to the arrays of routes.
        foreach ($route->getMethods() as $method) {
            $this->allRoutes[$method][$domainAndUri] = $route;
        }

        // Resolve Routing
        $this->routes->attach($route);
        $this->router->addRoute($route);

        return $route;
    }

    /**
     * {@inheritdoc}
     */
    public function getRoutes(): iterable
    {
        foreach ($this->routes as $route) {
            yield $route;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    /**
     * {@inheritdoc}
     */
    public function getMiddlewareDispatcher(): Middlewares\MiddlewareDisptcher
    {
        return $this->middlewareDispatcher;
    }

    /**
     * {@inheritdoc}
     */
    public function setRequest(ServerRequestInterface $request): RouteCollectorInterface
    {
        $this->request = $request;

        return $this;
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
    public function removeNamedRoute(string $name): RouteCollectorInterface
    {
        /** @var Route $route */
        $route = $this->getNamedRoute($name);
        $this->routes->detach($route);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getNamedRoute(string $name): RouteInterface
    {
        foreach ($this->routes as $route) {
            if ($name === $route->getName()) {
                return $route;
            }
        }

        throw new RuntimeException('Named route does not exist for name: '.$name);
    }

    /**
     * {@inheritdoc}
     */
    public function addLookupRoute(RouteInterface $route): void
    {
        if (null === $name = $route->getName()) {
            //throw new RuntimeException('Route not found, looks like your route cache is stale.');
            return;
        }

        $this->nameList[$name] = $route;
    }

    /**
     * {@inheritdoc}
     */
    public function group(array $attributes, $callable): RouteGroupInterface
    {
        // Backup current properties
        $oldGroupOption = $this->groupOptions;

        $routeCollectorProxy = new RouterProxy($this->request, $this->responseFactory, $this->router, $this);
        $prefixPattern = $attributes[RouteGroupInterface::PREFIX] ?? null;

        // Register the Route Grouping
        $routeGroup = new RouteGroup($prefixPattern, $attributes, $callable, $this->callableResolver, $routeCollectorProxy);

        // Add goups to RouteCollection
        $this->routeGroups[] = $routeGroup;
        $this->groupOptions = $this->resolveGlobals($routeGroup->getOptions());

        // Returns routes on closure, file or on callble
        $routeGroup->collectRoutes();
        array_shift($this->routeGroups);

        // Restore properties
        $this->groupOptions = $oldGroupOption;

        return $routeGroup;
    }

    /**
     * Register a new GET route with the router.
     *
     * @param string                    $uri
     * @param Closure|array|string|null $action
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
     * @param Closure|array|string|null $action
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
     * @param Closure|array|string|null $action
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
     * @param Closure|array|string|null $action
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
     * @param Closure|array|string|null $action
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
     * @param Closure|array|string|null $action
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
     * @param Closure|array|string|null $action
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
        return $this->addRoute(array_map('strtoupper', $methods), $pattern, $handler);
    }

    /**
     * {@inheritdoc}
     */
    public function setRoute(RouteInterface $route): void
    {
        assert($route instanceof Route);

        // Configure route with needed dependencies.
        $defaults = [
            'namespace' => $this->getGroupOption(RouteGroupInterface::NAMESPACE),
            'defaults'  => $this->getGroupOption(RouteGroupInterface::DEFAULTS),
            'patterns'  => $this->getGroupOption(RouteGroupInterface::REQUIREMENTS),
            'schemes'   => $this->getGroupOption(RouteGroupInterface::SCHEMES),
        ];

        $route->fromArray($defaults);

        // Resolve Routing
        $this->routes->attach($route);
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
    public function setNamespace(?string $rootNamespace = null): RouteCollectorInterface
    {
        if (isset($rootNamespace)) {
            $this->setGroupOption(RouteGroupInterface::NAMESPACE, $rootNamespace);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function generateUri(string $routeName, array $parameters = [], array $queryParams = []): ?string
    {
        if (isset($this->nameList[$routeName]) === false) {
            throw new UrlGenerationException(sprintf('Unable to generate a URL for the named route "%s" as such route does not exist.', $routeName), 404);
        }

        // First we will construct the entire URI including the root. Once it
        // has been constructed, we'll pass the remaining for further parsing.
        $uri = $this->router->generateUri($this->getNamedRoute($routeName), $parameters);
        $uri = str_replace('/?', '', rtrim($uri, '/'));

        // Resolve port and domain for better url generated from route by name.
        $domain = '.';
        $requestUri = $this->request->getUri();

        // Resolve domains with port enabled
        if (null !== $requestUri->getPort() && !in_array($requestUri->getPort(), [80, 443], true)) {
            $domain = "{$requestUri->getScheme()}://{$requestUri->getHost()}:{$requestUri->getPort()}";
        }

        // Making routing on sub-folders easier
        if (strpos($uri, '/') !== 0) {
            $domain .= '/';
        }

        // Incase query is added to uri.
        if (!empty($queryParams)) {
            $separator = ini_get('arg_separator.input');
            $uri .= '?'.http_build_query($queryParams, '', $separator ? $separator[0] : '&');
        }

        return $domain.$uri;
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
    public function dispatch(): ResponseInterface
    {
        // Add Route Names RouteCollector.
        $routes = $this->getRoutes();
        array_walk($routes, [$this, 'addLookupRoute']);

        $routingResults = $this->router->match($this->request);
        $this->currentRoute = $route = $routingResults->getMatchedRoute();

        // Get all available middlewares
        $routingResults->determineResponseCode($this->request, $this->keepRequestMethod);
        $middlewares = array_merge($route ? $route->getMiddlewares() : [], $this->getMiddlewaresStack());

        // Allow Middlewares to be disabled
        if (
            in_array('off', $middlewares, true) ||
            in_array('disable', $middlewares, true)
        ) {
            $middlewares = [];
        }

        if (count($middlewares) > 0) {
            // This middleware is in the priority map. If we have encountered another middleware
            // that was also in the priority map and was at a lower priority than the current
            // middleware, we will move this middleware to be above the previous encounter.
            $middleware = $this->middlewareDispatcher->pipeline($middlewares);

            try {
                $requestHandler = $this->middlewareDispatcher->addhandler($routingResults);
            } finally {
                // This middleware is in the priority map; but, this is the first middleware we have
                // encountered from the map thus far. We'll save its current index plus its index
                // from the priority map so we can compare against them on the next iterations.
                return $middleware->process($this->request, $requestHandler);
            }
        }

        return $routingResults->handle($this->request);
    }

    /**
     * Get Route The Group Option.
     *
     * @param string $name
     *
     * @return mixed
     */
    protected function getGroupOption(string $name)
    {
        return $this->groupOptions[$name] ?? null;
    }

    /**
     * Set the Route Group Option.
     *
     * @param string $name
     * @param mixed  $value
     */
    protected function setGroupOption(string $name, $value): void
    {
        $this->groupOptions[$name] = $value;
    }

    /**
     * Resolving patterns and defaults to group.
     *
     * @param array $groupOptions
     *
     * @return array
     */
    protected function resolveGlobals(array $groupOptions): array
    {
        $groupOptions[RouteGroup::REQUIREMENTS] = array_merge(
            $this->getGroupOption(RouteGroup::REQUIREMENTS) ?? [],
            $groupOptions[RouteGroup::REQUIREMENTS] ?? []
        );

        $groupOptions[RouteGroup::DEFAULTS] = array_merge(
            $this->getGroupOption(RouteGroup::DEFAULTS) ?? [],
            $groupOptions[RouteGroup::DEFAULTS] ?? []
        );

        return $groupOptions;
    }

    /**
     * @return array
     */
    public function __debugInfo()
    {
        return [
            'routes'    => $this->allRoutes,
            'current'   => $this->currentRoute,
            'counts'    => count($this->getRouter()),
        ];
    }
}
