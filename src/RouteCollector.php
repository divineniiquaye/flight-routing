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
use Flight\Routing\Services\HttpPublisher;
use BiuradPHP\Http\Exceptions\ClientExceptions;
use BiuradPHP\Http\Interfaces\EmitterInterface;
use Flight\Routing\RouteResource as Resource;
use Flight\Routing\Interfaces\RouterInterface;
use Flight\Routing\Exceptions\RouteNotFoundException;
use Flight\Routing\Exceptions\UrlGenerationException;
use Psr\Http\Message\ResponseInterface;
use Flight\Routing\Exceptions\InvalidControllerException;
use Flight\Routing\Exceptions\InvalidMiddlewareException;
use BiuradPHP\DependencyInjection\Interfaces\FactoryInterface;

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
 * - match
 * - resource
 *
 * A general `match()` method allows specifying multiple request methods and/or
 * arbitrary request methods when creating a path-based route.
 *
 * Internally, the class performs some checks for duplicate routes when
 * attaching via one of the exposed methods, and will raise an exception when a
 * collision occurs.
 *
 * TODO: Add url Query Support in next update
 */
class RouteCollector
{
    use Concerns\RouteGroup, Concerns\RouteResolver;

    /** @var @internal used by the `run` method */
    private $arguments;

    /** @var array|Route[]string */
    private $routes = [];

    /** @var RouterInterface */
    private $collection;

    /** @var array|string[] */
    public $routeMiddlewares = [];

    /** @var Route[]string */
    private $nameList = [];

    /** @var bool */
    private $disableMiddlewares = false;

    /**
     * Characters that should not be URL encoded.
     *
     * @var array
     */
    public const DONT_ENCODE = [
        '%2F' => '/',
        '%40' => '@',
        '%3A' => ':',
        '%3B' => ';',
        '%2C' => ',',
        '%3D' => '=',
        '%2B' => '+',
        '%21' => '!',
        '%2A' => '*',
        '%7C' => '|',
        '%3F' => '?',
        '%26' => '&',
        '%23' => '#',
        '%25' => '%',
    ];

    /**
     * Router constructor.
     *
     * @param ServerRequestInterface $request
     * @param RouterInterface|null $router
     * @param FactoryInterface|null $container
     * @param EmitterInterface|null $emitter
     */
    public function __construct(ServerRequestInterface $request, RouterInterface $router = null, ?EmitterInterface $emitter = null, ?FactoryInterface $container = null)
    {
        $this->prepare($container, $request, $emitter);
        $this->collection = $router ?: new RouteMatcher();
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
        // Resolve prefix + uri.
        if ($prefix = $this->currentPrefix) {
            $uri = $this->normalizePrefix($uri, $prefix);
        }

        $route = $this->newRoute($methods, $uri, $action);

        // Set the defualts for group routing.
        $defaults = [
            'name' => $this->currentName,
            'prefix' => $this->currentPrefix,
            'domain' => $this->currentDomain,
            'namespace' => $this->currentNamespace,
            'middleware' => $this->currentMiddleware ?: [],
        ];

        $route->fromArray($defaults);

        return $route;
    }

    /**
     * Ensures that the right-most slash is trimmed for prefixes of more than
     * one character, and that the prefix begins with a slash.
     *
     * @param string $uri
     * @param mixed $prefix
     */
    protected function normalizePrefix(string $uri, $prefix)
    {
        $urls = [];
        foreach (['&', '-', '_', '~', '@'] as $symbols) {
            if (mb_strpos($prefix, $symbols) !== false) {
                $urls[] = rtrim($prefix, '/') . $uri;
            }
        }

        return $urls ? $urls[0] : rtrim($prefix, '/') . '/' . ltrim($uri, '/');
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
        $route = new Route($this, $methods, $uri, $action);

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
     * @throws RuntimeException when called after match() have been called.
     */
    protected function addRoute($methods, $uri, $action)
    {
        $route = $this->createRoute($methods, $uri, $action);
        $domainAndUri = $route->getDomain() . $route->getUri();

        // Resolve method to array
        $methods = $route->getMethod();

        // Add the given route to the arrays of routes.
        foreach ($methods as $method) {
            $this->routes[$method][$domainAndUri] = $route;
        }

        // Resolve Routing
        $this->collection->addRoute($route);

        return $route;
    }

    /**
     * Prepare router to dispatch routes.
     *
     * @param \BiuradPHP\DependencyInjection\Interfaces\FactoryInterface|null      $container
     * @param \Psr\Http\Message\ServerRequestInterface    $request
     * @param \BiuradPHP\Http\Interfaces\EmitterInterface $emitter
     */
    private function prepare(?FactoryInterface $container, ServerRequestInterface $request, EmitterInterface $emitter): void
    {
        $this->container = $container;

        if ($this->request == null) {
            $this->setRequest($request);
        }

        if ($this->emitter == null) {
            $this->setEmitter($emitter);
        }

        if ($this->publisher == null) {
            $this->setPublisher(new HttpPublisher());
        }
    }

    /**
     * Throw an not found error.
     *
     * @param null $matched
     */
    private function notFoundError($matched)
    {
        if (is_null($matched)) {
            throw new RouteNotFoundException(sprintf('No route detected on path ["%s"]', $this->request->getUri()->getPath()));
        }
    }

    /**
     * Run the controller of the given route.
     *
     * @param Route|null $route
     * @param array      $parameters
     *
     * @return mixed|ResponseInterface
     *
     * @throws InvalidControllerException
     * @throws InvalidMiddlewareException
     * @throws Throwable
     */
    private function run($route, ?array $parameters)
    {
        // Controller and namspace.
        $controller =   $route->getController();
        $middlewares =  array_replace($this->currentMiddleware, $route->getMiddleware());

        // Let's allow the developer to disable middlewares
        if (false !== $this->disableMiddlewares) {
            $middlewares = [];
        } elseif (false !== $route->disabledMiddlewares()) {
            $middlewares = [];
        } elseif (in_array('off', $middlewares)) {
            $middlewares = [];
        }

        if (count($middlewares) > 0) {
            $controllerRunner = $this->resolveController($controller, $parameters);

            return $this->runControllerThroughMiddleware($middlewares, $this->request, $controllerRunner);
        }

        return $this->runController($controller, $parameters, $this->request);
    }

    /**
     * This method is to allow middlewares or not in your application.
     * Maybe you working on a small project which does not require middlewares.
     * You can disable it by setting $all to true, or specify the middlewares
     * for removal.
     *
     * NB: You only allowed to remove global and route global middlewares.
     */
    public function disableMiddlewares(bool $all = false, ...$middlewares)
    {
        if (false !== $all) {
            return $this->disableMiddlewares = true;
        }

        if (is_array($middlewares[0])
            && count($middlewares) === 1
        ) {
            $middlewares = array_shift($middlewares);
        }

        foreach ($middlewares as $middleware) {
            if (array_key_exists($middleware, $this->routeMiddlewares)) {
                unset($this->routeMiddlewares[$middleware]);
            } elseif (array_key_exists($middleware, $this->currentMiddleware)) {
                unset($this->currentMiddleware[$middleware]);
            }
        }

        return true;
    }

    /**
     * Refresh the name look-up table.
     *
     * This is done in case any names are fluently defined or if routes are overwritten.
     *
     * @param Route $route
     */
    public function nameLookup(Route $route)
    {
        if ($name = $route->getName()) {
            $this->nameList[$name] = $route;
        }
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
        return $this->match([HttpMethods::METHOD_GET, HttpMethods::METHOD_HEAD], $uri, $action);
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
        return $this->match(HttpMethods::METHOD_POST, $uri, $action);
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
        return $this->match(HttpMethods::METHOD_PUT, $uri, $action);
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
        return $this->match(HttpMethods::METHOD_PATCH, $uri, $action);
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
        return $this->match(HttpMethods::METHOD_DELETE, $uri, $action);
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
        return $this->match(HttpMethods::METHOD_OPTIONS, $uri, $action);
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
        return $this->match(HttpMethods::HTTP_METHODS_STANDARD, $uri, $action);
    }

    /**
     * Register a new route with the given verbs.
     *
     * @param array|string               $methods
     * @param string                     $uri
     * @param \Closure|array|string|null $action
     *
     * @return \Flight\Routing\Route
     */
    public function match($methods, $uri, $action = null)
    {
        return $this->addRoute(array_map('mb_strtoupper', (array) $methods), $uri, $action);
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
     * Set the controller as Api Resource Controller.
     *
     * Router knows how to respond to resource controller
     * request automatically
     *
     * @param string                  $uri
     * @param Closure|callable|string $controller
     * @param array                   $options
     */
    public function resource($name, $controller, array $options = [])
    {
        $registrar = new Resource($this);

        return $registrar->register($name, $controller, $options);
    }

    /**
     * Set the global the middlewares stack attached to all routes.
     *
     * @param array|string|null $middleware
     *
     * @return $this|array
     */
    public function globalMiddlewares($middleware = [])
    {
        $middleware = array_diff($middleware, $this->currentMiddleware);

        $this->currentMiddleware = array_merge($this->currentMiddleware, $middleware);

        return $this;
    }

    /**
     * Set the route middleware and call it as a method on route.
     *
     * @param array $middlewares
     *
     * @return $this|array
     */
    public function routeMiddlewares($middlewares = [])
    {
        foreach ($middlewares as $name => $action) {
            $this->routeMiddlewares[$name] = $action;
        }

        return $this;
    }

    /**
     * Get the value of routeMiddlewares
     *
     * @return array|string
     */
    public function getRouteMiddleware(string $middleware)
    {
        return $this->routeMiddlewares[$middleware];
    }

    /**
     * Set the root controller namespace.
     *
     * @param string $rootNamespace
     *
     * @return $this
     */
    public function namespace($rootNamespace = null)
    {
        if (isset($rootNamespace)) {
            $this->currentNamespace = $rootNamespace;
        }

        return $this;
    }

    /**
     * Get a route instance by its name.
     *
     * @param string $name
     *
     * @return \Flight\Routing\Route|null
     */
    public function getByName($name)
    {
        return isset($this->nameList[$name]) ? $this->nameList[$name] : null;
    }

    /**
     * Generate a URI from the named route.
     *
     * Takes the named route and any parameters, and attempts to generate a
     * URI from it. Additional router-dependent options may be passed.
     *
     * The URI generated MUST NOT be escaped. If you wish to escape any part of
     * the URI, this should be performed afterwards.
     *
     * @param string         $routeName  route name
     * @param string[]|array $parameters key => value option pairs to pass to the
     *                                   router for purposes of generating a URI; takes precedence over options
     *                                   present in route used to generate URI
     *
     * @return string URI path generated
     *
     * @throws UrlGenerationException if the route name is not known
     *                                or a parameter value does not match its regex
     */
    public function generateUri(string $routeName, array $parameters = []): ?string
    {
        if (isset($this->nameList[$routeName]) == false) {
            throw new UrlGenerationException(sprintf('Unable to generate a URL for the named route "%s" as such route does not exist.', $routeName), 404);
        }

        $uri = $this->getByName($routeName)->getUri();
        $defaults = $this->getByName($routeName)->getDefault(null);

        // First we will construct the entire URI including the root. Once it
        // has been constructed, we'll pass the remaining for further parsing.
        $uri = preg_replace_callback('/\??\{(.*?)\??\}/', function ($match) use (&$parameters, $defaults) {
            if (isset($parameters[$match[1]])) {
                return $parameters[$match[1]];
            } elseif (array_key_exists($match[1], $defaults)) {
                return $defaults[$match[1]];
            }

            return  $match[0];
        }, $uri);

        // We'll make sure we don't have any missing parameters or we
        // will need to throw the exception to let the developers know one was not given.
        $uri = preg_replace_callback('/\{(.*?)(\?)?\}/', function ($match) use (&$defaults, $routeName) {
            if (! array_key_exists($match[1], $defaults)) {
                throw UrlGenerationException::forMissingParameters($this->getByName($routeName));
            }

            return '';
        }, $uri);

        // Once we have ensured that there are no missing parameters in the URI we will encode
        // the URI and prepare it for returning to the developer. If the URI is supposed to
        // be absolute, we will return it as-is. Otherwise we will remove the URL's root.
        $uri = strtr(rawurlencode($uri), self::DONT_ENCODE);
        $uri = str_replace('/?', '', rtrim($uri, "/"));
        $domain = '.';

        // Add the domain to the given route if necessary.
        $scheme = $this->getRequest()->getUri()->getScheme();
        if ($this->getByName($routeName)->getDomain()) {
            $domain = "{$scheme}://{$domain}";
        }

        // Making routing on sub-folders easier
        if (mb_strpos($uri, '/') !== 0) {
            $domain = $domain . '/';
        }

        return $domain.$uri;
    }

    /**
     * Get the current route.
     *
     * @return Route|null
     */
    public function currentRoute(): ?Route
    {
        return $this->currentRoute;
    }

    /**
     * Get the RouteMatcher Collection
     *
     * @return RouterInterface
     */
    public function getCollection()
    {
        return $this->collection;
    }

    /**
     * Dispatch routes and run the application.
     *
     * @return $this
     *
     * @throws RouteNotFoundException
     * @throws Throwable
     */
    public function dispatch()
    {
        $method = $this->request->getMethod();

        if (!array_key_exists($method, $this->routes)) {
            throw new ClientExceptions\MethodNotAllowedException();
        }

        // check if route is defined without regex
        [$matched] = $this->collection->match($this->request, $this);

        // Throw an exception if route not found
        if (null === $matched) {
            $this->notFoundError($matched);
        }

        $this->arguments = $matched[1];
        $this->currentRoute = $matched[0];

        $this->publisher->publish(
            $this->run($this->currentRoute, $this->arguments), $this->getEmitter()
        );

        return $this;
    }
}
