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

use Flight\Routing\Concerns\HttpMethods;
use Psr\Http\Message\ServerRequestInterface;
use BiuradPHP\Http\Exceptions\ClientExceptions;
use Flight\Routing\RouteResource as Resource;
use Flight\Routing\Exceptions\RouteNotFoundException;
use Flight\Routing\Exceptions\UrlGenerationException;
use Psr\Http\Message\ResponseInterface;
use Flight\Routing\Interfaces\CallableResolverInterface;
use Flight\Routing\Interfaces\RouteCollectorInterface;
use Flight\Routing\Interfaces\RouteGroupInterface;
use Flight\Routing\Interfaces\RouteInterface;
use Flight\Routing\Interfaces\RouterInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

use function file_exists;
use function is_readable;
use function is_writable;
use function dirname;
use function sprintf;
use function array_push;
use function http_build_query;
use function str_replace;
use function in_array;
use function array_merge;
use function array_walk;
use function array_shift;
use function mb_strpos;

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
     * @var Route[]
     */
    private $routes = [];

    /**
     * @var RouteMiddleware
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
     * Base path used in pathFor()
     *
     * @var string
     */
    protected $basePath = null;

    /**
     * Path to fast route cache file. Set to false to disable route caching
     *
     * @var string|null
     */
    protected $cacheFile;

    /**
     * @var bool
     */
    protected $permanent = true;

    /**
     * Route groups
     *
     * @var RouteGroup[]
     */
    protected $routeGroups = [];

    /**
     * Route Group Options
     *
     * @var array
     */
    protected $groupOptions = [];

    /**
     * Route counter incrementer
     *
     * @var int
     */
    protected $routeCounter = 0;

    /**
     * @var ResponseFactoryInterface
     */
    protected $responseFactory;

    /**
     * @param ServerRequestInterface     $request
     * @param ResponseFactoryInterface   $responseFactory
     * @param RouterInterface|null       $router
     * @param CallableResolverInterface  $callableResolver
     * @param ContainerInterface|null    $container
     * @param string                     $cacheFile
     */
    public function __construct(
        ServerRequestInterface $request,
        ResponseFactoryInterface $responseFactory,
        RouterInterface $router = null,
        CallableResolverInterface $callableResolver = null,
        ContainerInterface $container = null,
        string $cacheFile = null
    ) {
        $this->request = $request;
        $this->responseFactory = $responseFactory;
        $this->router = $router ?? new Services\DefaultFlightRouter();

        $this->container = $container;
        $this->callableResolver = $callableResolver ?? new Concerns\CallableResolver($container);
        $this->middlewareDispatcher = new RouteMiddleware([], $this->container);

        if (null !== $cacheFile) {
            $this->setCacheFile($cacheFile);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheFile(): ?string
    {
        return $this->cacheFile;
    }

    /**
     * {@inheritdoc}
     */
    public function setCacheFile(string $cacheFile): RouteCollectorInterface
    {
        if (file_exists($cacheFile) && !is_readable($cacheFile)) {
            throw new \RuntimeException(
                sprintf('Route collector cache file `%s` is not readable', $cacheFile)
            );
        }

        if (!file_exists($cacheFile) && !is_writable(dirname($cacheFile))) {
            throw new \RuntimeException(
                sprintf('Route collector cache file directory `%s` is not writable', dirname($cacheFile))
            );
        }

        $this->cacheFile = $cacheFile;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getBasePath(): string
    {
        $basePath = $this->basePath ?? dirname($this->request->getServerParams()['SCRIPT_NAME'] ?? '');

        // For phpunit testing to be smooth.
        if ('cli' === PHP_SAPI) {
            $basePath = '';
        }

        return $basePath;
    }

    /**
     * {@inheritdoc}
     */
    public function setBasePath(string $basePath): RouteCollectorInterface
    {
        $this->basePath = $basePath;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addParameters(array $parameters, int $type = self::TYPE_REQUIREMENT): RouteCollectorInterface
    {
        foreach ($parameters as $key => $regex) {
            if (self::TYPE_DEFAULT == $type) {
                $this->setGroupOption(RouteGroupInterface::DEFAULTS, [$key => $regex]);
                break;
            }

            $this->setGroupOption(RouteGroupInterface::REQUIREMENTS, [$key => $regex]);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setPermanentRedirection(bool $permanent = true): RouteCollectorInterface
    {
        $this->permanent = $permanent;

        return $this;
    }

    /**
     * Create a new route instance.
     *
     * @param array|string $methods
     * @param string       $uri
     * @param mixed        $action
     *
     * @return \Flight\Routing\Route
     */
    protected function createRoute($methods, $uri, $action)
    {
        $route = $this->newRoute($methods, $uri, $action);

        // Set the defualts for group routing.
        $defaults = [
            'namespace' => $this->getGroupOption(RouteGroupInterface::NAMESPACE),
            'defaults'  => $this->getGroupOption(RouteGroupInterface::DEFAULTS),
            'patterns'  => $this->getGroupOption(RouteGroupInterface::REQUIREMENTS),
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
     * @return \Flight\Routing\Route
     */
    protected function newRoute($methods, $uri, $action)
    {
        $route = new Route(
            (array) $methods,
            $uri,
            $action,
            $this->callableResolver,
            $this->middlewareDispatcher,
            $this->responseFactory->createResponse(),
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
     * @param array|array                $methods HTTP method to accept
     * @param string|null                $uri     the uri of the route
     * @param \Closure|array|string|null $action  a requesthandler or controller
     *
     * @return \Flight\Routing\Route
     *
     * @throws \RuntimeException when called after match() have been called.
     */
    protected function addRoute($methods, $uri, $action)
    {
        $route = $this->createRoute($methods, $uri, $action);
        $domainAndUri = $route->getDomain() . $route->getPath();

        // Add the given route to the arrays of routes.
        foreach ($route->getMethods() as $method) {
            $this->allRoutes[$method][$domainAndUri] = $route;
        }

        // Resolve Routing
        array_push($this->routes, $route);
        $this->router->addRoute($route);

        return $route;
    }

    /**
     * {@inheritdoc}
     */
    public function getRoutes(): array
    {
        return $this->routes;
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
    public function removeNamedRoute(string $name): RouteCollectorInterface
    {
        /** @var Route $route */
        $route = $this->getNamedRoute($name);
        unset($this->routes[$route->getName()]);

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
        throw new \RuntimeException('Named route does not exist for name: ' . $name);
    }

    /**
     * {@inheritdoc}
     */
    public function addLookupRoute(RouteInterface $route): void
    {
        if (null === $name = $route->getName()) {
            //throw new \RuntimeException('Route not found, looks like your route cache is stale.');
            return;
        }

        $this->nameList[$name] = $route;
    }

    /**
     * {@inheritdoc}
     */
    public function group(array $attributes = [], $callable): RouteGroupInterface
    {
        // Backup current properties
        $oldGroupOption = $this->groupOptions;

        $routeCollectorProxy = new RouterProxy($this->request, $this->responseFactory, $this->router, $this);
        $prefixPattern = isset($attributes[RouteGroupInterface::PREFIX]) ? $attributes[RouteGroupInterface::PREFIX] : null;

        // Register the Route Grouping
        $routeGroup = new RouteGroup($prefixPattern, $attributes, $callable, $this->callableResolver, $routeCollectorProxy);

        // Add goups to RouteCollection
        $this->routeGroups[] = $routeGroup;
        $this->groupOptions  = $this->resolveGlobals($routeGroup->getOptions());

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
     * @param string                     $uri
     * @param \Closure|array|string|null $action
     *
     * @return \Flight\Routing\Route
     */
    public function get($uri, $action = null)
    {
        return $this->map([HttpMethods::METHOD_GET, HttpMethods::METHOD_HEAD], $uri, $action);
    }

    /**
     * Register a new POST route with the router.
     *
     * @param string                     $uri
     * @param \Closure|array|string|null $action
     *
     * @return \Flight\Routing\Route
     */
    public function post($uri, $action = null)
    {
        return $this->map([HttpMethods::METHOD_POST], $uri, $action);
    }

    /**
     * Register a new PUT route with the router.
     *
     * @param string                     $uri
     * @param \Closure|array|string|null $action
     *
     * @return \Flight\Routing\Route
     */
    public function put($uri, $action = null)
    {
        return $this->map([HttpMethods::METHOD_PUT], $uri, $action);
    }

    /**
     * Register a new PATCH route with the router.
     *
     * @param string                     $uri
     * @param \Closure|array|string|null $action
     *
     * @return \Flight\Routing\Route
     */
    public function patch($uri, $action = null)
    {
        return $this->map([HttpMethods::METHOD_PATCH], $uri, $action);
    }

    /**
     * Register a new DELETE route with the router.
     *
     * @param string                     $uri
     * @param \Closure|array|string|null $action
     *
     * @return \Flight\Routing\Route
     */
    public function delete($uri, $action = null)
    {
        return $this->map([HttpMethods::METHOD_DELETE], $uri, $action);
    }

    /**
     * Register a new OPTIONS route with the router.
     *
     * @param string                     $uri
     * @param \Closure|array|string|null $action
     *
     * @return \Flight\Routing\Route
     */
    public function options($uri, $action = null)
    {
        return $this->map([HttpMethods::METHOD_OPTIONS], $uri, $action);
    }

    /**
     * Register a new route responding to all verbs.
     *
     * @param string                     $uri
     * @param \Closure|array|string|null $action
     *
     * @return \Flight\Routing\Route
     */
    public function any($uri, $action = null)
    {
        return $this->map([HttpMethods::HTTP_METHODS_STANDARD], $uri, $action);
    }

    /**
     * {@inheritdoc}
     */
    public function map(array $methods, $uri, $action = null): RouteInterface
    {
        $this->routeCounter++;
        return $this->addRoute(array_map('mb_strtoupper', $methods), $uri, $action);
    }

    /**
     * Set the global resource parameter mapping.
     *
     * @param array $parameters
     */
    public function resourceParameters(array $parameters = [])
    {
        return Resource::setParameters($parameters);
    }

    /**
     * Get or set the verbs used in the resource URIs.
     *
     * @param array $verbs
     *
     * @return array|null
     */
    public function resourceVerbs(array $verbs = [])
    {
        return Resource::verbs($verbs);
    }

    /**
     * Register an array of resource controllers.
     *
     * @param array $resources
     */
    public function resources(array $resources)
    {
        foreach ($resources as $name => $controller) {
            $this->resource($name, $controller);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function resource($name, $controller, array $options = [])
    {
        $registrar = new Resource($this);

        return $registrar->register($name, $controller, $options);
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
        if (isset($this->nameList[$routeName]) == false) {
            throw new UrlGenerationException(sprintf('Unable to generate a URL for the named route "%s" as such route does not exist.', $routeName), 404);
        }

        // First we will construct the entire URI including the root. Once it
        // has been constructed, we'll pass the remaining for further parsing.
        $uri = $this->router->generateUri($route = $this->getNamedRoute($routeName), $parameters);
        $uri = str_replace('/?', '', rtrim($uri, "/"));

        // Resolve port and domain for better url generated from route by name.
        $domain = '.';
        $requestUri = $this->request->getUri();

        // Resolve domains with port enabled
        if (null !== $requestUri->getPort() && !in_array($requestUri->getPort(), [80, 443], true)) {
            $domain = "{$requestUri->getScheme()}://{$requestUri->getHost()}:{$requestUri->getPort()}";
        }

        // Add the domain to the given route if necessary.
        if ($routeDomain = $route->getDomain()) {
            $domain = "{$requestUri->getScheme()}://{$routeDomain}";
        }

        // Making routing on sub-folders easier
        if (mb_strpos($uri, '/') !== 0) {
            $domain .=  '/';
        }

        // Incase query is added to uri.
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

        return $domain . $uri;
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
    public function dispatch(): ResponseInterface
    {
        $routes     = $this->getRoutes();
        $uriPath    = $this->request->getUri()->getPath();

        // Add Route Names RouteCollector.
        array_walk($routes, [$this, 'addLookupRoute']);

        $routingResults = $this->router->match($this->request);
        $routeStatus    = $routingResults->getRouteStatus();

        switch ($routeStatus) {
            case RouteResults::FOUND:
                if (null !== $this->logger) {
                    $routingResults->setLogger($this->logger);
                }

                $this->currentRoute = $routingResults->getRouteIdentifier();
                $response           = $routingResults->handle($this->request);

                // Allow Redirection if exists and avoid static request.
                if ($response->hasHeader('Location') && null !== $routingResults->getRedirectLink()) {
                    $response = $response->withStatus($this->determineResponseCode($this->request));
                }

                return $response;

            case RouteResults::NOT_FOUND:
                $exception = new  RouteNotFoundException();
                $exception->withMessage(sprintf('Unable to find the controller for path "%s". The route is wrongly configured.', $uriPath));

                throw $exception;

            case RouteResults::METHOD_NOT_ALLOWED:
                throw new ClientExceptions\MethodNotAllowedException();

            default:
                throw new \DomainException('An unexpected error occurred while performing routing.');
        }
    }

    /**
     * Determine the response code according with the method and the permanent config
     */
    public function determineResponseCode(ServerRequestInterface $request): int
    {
        if (in_array($request->getMethod(), ['GET', 'HEAD', 'CONNECT', 'TRACE', 'OPTIONS'])) {
            return $this->permanent ? 301 : 302;
        }

        return $this->permanent ? 308 : 307;
    }

    /**
     * Get Route The Group Option.
     *
     * @param string $name
     * @return mixed
     */
    protected function getGroupOption(string $name)
    {
        return isset($this->groupOptions[$name]) ? $this->groupOptions[$name] : null;
    }

    /**
     * Set the Route Group Option
     *
     * @param string $name
     * @param mixed $value
     */
    protected function setGroupOption(string $name, $value): void
    {
        $this->groupOptions[$name] = $value;
    }

    /**
     * Resolving patterns and defaults to group
     *
     * @param array $groupOptions
     */
    private function resolveGlobals(array $groupOptions): array
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
            'counts'    => $this->routeCounter
        ];
    }
}
