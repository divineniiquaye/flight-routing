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

/**
 * A RouteCollection represents a set of Route instances.
 *
 * This class provides all(*) methods for creating path+HTTP method-based routes and
 * injecting them into the router:
 *
 * - head
 * - get
 * - post
 * - put
 * - patch
 * - delete
 * - options
 * - any
 * - resource
 *
 * A general `addRoute()` method allows specifying multiple request methods and/or
 * arbitrary request methods when creating a path-based route.
 *
 * __call() forwards method-calls to Route, but returns instance of RouteCollection
 * listing Route's methods below, so that IDEs know they are valid
 *
 * @method RouteCollection withAssert(string $variable, string|string[] $regexp)
 * @method RouteCollection withDefault(string $variable, mixed $default)
 * @method RouteCollection withArgument($variable, mixed $value)
 * @method RouteCollection withMethod(string ...$methods)
 * @method RouteCollection withScheme(string ...$schemes)
 * @method RouteCollection withMiddleware(mixed ...$middlewares)
 * @method RouteCollection withDomain(string ...$hosts)
 * @method RouteCollection withPrefix(string $path)
 *
 * @method RouteCollection withDefaults(array $values)
 * @method RouteCollection withAsserts(array $patterns)
 * @method RouteCollection withArguments(array $patterns)
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Tobias Schultze <http://tobion.de>
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
final class RouteCollection implements \IteratorAggregate, \Countable
{
    /** @var null|string */
    private $namePrefix;

    /** @var Route */
    private $defaultRoute;

    /** @var Route[] */
    private $routes = [];

    /**
     * @param null|Route $defaultRoute
     * @param mixed      $defaultHandler
     */
    public function __construct(?Route $defaultRoute = null, $defaultHandler = null)
    {
        $this->defaultRoute = $defaultRoute ?? new Route('/', '', $defaultHandler);
    }

    /**
     * @param string   $method
     * @param string[] $arguments
     *
     * @return mixed
     */
    public function __call($method, $arguments)
    {
        $routeMethod = (string) \preg_replace('/^(default|assert)(s)|with([A-Z]{1}[a-z]+)$/', '\2\3', $method, 1);
        $excluded    = !in_array($routeMethod = \strtolower($routeMethod), ['arguments', 's'], true);

        if (!(\method_exists($this->defaultRoute, $routeMethod) || $excluded)) {
            throw new \BadMethodCallException(
                \sprintf(
                    'Method "%s::%s" does not exist. %2$s method should begin a \'with\' prefix',
                    Route::class,
                    $routeMethod ?: $method
                )
            );
        }

        \call_user_func_array([$this->defaultRoute, $routeMethod], $arguments);

        foreach ($this->routes as $route) {
            \call_user_func_array([$route, $routeMethod], $arguments);
        }

        return $this;
    }

    /**
     * Gets the number of Routes in this collection.
     *
     * @return int The number of routes
     */
    public function count()
    {
        return \count($this->getRoutes());
    }

    /**
     * Gets the current RouteCollection as an iterable of routes.
     *
     * This method can be used to fetch routes too, but if group() method
     * is used, use getRoutes() method instead.
     *
     * @return \ArrayIterator<int,Route> The unfiltered routes
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->routes);
    }

    /**
     * Gets the filtered RouteCollection as an array that includes all routes.
     *
     * Use this method to fetch routes instead of getIterator().
     *
     * @return Route[] The filtered merged routes
     */
    public function getRoutes(): array
    {
        return $this->doMerge('', new static());
    }

    /**
     * Add route(s) to the collection.
     *
     * This method unsets all setting from default route and use new settings
     * from new the route(s). If you want the default settings to be merged
     * into routes, use `addRoute` method instead.
     *
     * @param Route ...$routes
     *
     * @return self
     */
    public function add(Route ...$routes): self
    {
        foreach ($routes as $route) {
            $default = clone $this->defaultRoute;

            // Append default path to routes' path
            $route->prefix($default->getPath());

            // Merge defaults with route
            $mergedRoute    = array_merge($default->getAll(), $route->getAll());
            $this->routes[] = $default::__set_state($mergedRoute);
        }

        return $this;
    }

    /**
     * Maps a pattern to a handler.
     *
     * You can must specify HTTP methods that should be matched.
     *
     * @param string   $pattern Matched route pattern
     * @param string[] $methods Matched HTTP methods
     * @param mixed    $handler Handler that returns the response when matched
     *
     * @return Route
     */
    public function addRoute(string $pattern, array $methods, $handler = null): Route
    {
        $route      = clone $this->defaultRoute;
        $controller = null === $handler ? $route->getController() : $handler;

        $route->prefix($route->getPath())->path($pattern)->method(...$methods);

        $this->routes[] = $route;
        $route->run($controller);

        return $route;
    }

    /**
     * Mounts controllers under the given route prefix.
     *
     * @param string                   $name        The route group prefixed name
     * @param callable|RouteCollection $controllers A RouteCollection instance or a callable for defining routes
     *
     * @throws \LogicException
     */
    public function group(string $name, $controllers): self
    {
        if (\is_callable($controllers)) {
            $controllers($collection = new static());
            $controllers = clone $collection;
        } elseif (!$controllers instanceof self) {
            throw new \LogicException('The "group" method takes either a "RouteCollection" instance or callable.');
        }

        $controllers->namePrefix = $name;

        $this->routes[] = $controllers;

        return $controllers;
    }

    /**
     * Maps a HEAD request to a handler.
     *
     * @param string $pattern Matched route pattern
     * @param mixed  $handler Handler that returns the response when matched
     *
     * @return Route
     */
    public function head(string $pattern, $handler = null): Route
    {
        return $this->addRoute($pattern, [Router::METHOD_HEAD], $handler);
    }

    /**
     * Maps a GET and HEAD request to a handler.
     *
     * @param string $pattern Matched route pattern
     * @param mixed  $handler Handler that returns the response when matched
     *
     * @return Route
     */
    public function get(string $pattern, $handler = null): Route
    {
        return $this->addRoute($pattern, [Router::METHOD_GET, Router::METHOD_HEAD], $handler);
    }

    /**
     * Maps a POST request to a handler.
     *
     * @param string $pattern Matched route pattern
     * @param mixed  $handler Handler that returns the response when matched
     *
     * @return Route
     */
    public function post(string $pattern, $handler = null): Route
    {
        return $this->addRoute($pattern, [Router::METHOD_POST], $handler);
    }

    /**
     * Maps a PUT request to a handler.
     *
     * @param string $pattern Matched route pattern
     * @param mixed  $handler Handler that returns the response when matched
     *
     * @return Route
     */
    public function put(string $pattern, $handler = null): Route
    {
        return $this->addRoute($pattern, [Router::METHOD_PUT], $handler);
    }

    /**
     * Maps a PATCH request to a handler.
     *
     * @param string $pattern Matched route pattern
     * @param mixed  $handler Handler that returns the response when matched
     *
     * @return Route
     */
    public function patch(string $pattern, $handler = null): Route
    {
        return $this->addRoute($pattern, [Router::METHOD_PATCH], $handler);
    }

    /**
     * Maps a DELETE request to a handler.
     *
     * @param string $pattern Matched route pattern
     * @param mixed  $handler Handler that returns the response when matched
     *
     * @return Route
     */
    public function delete(string $pattern, $handler = null): Route
    {
        return $this->addRoute($pattern, [Router::METHOD_DELETE], $handler);
    }

    /**
     * Maps a OPTIONS request to a handler.
     *
     * @param string $pattern Matched route pattern
     * @param mixed  $handler Handler that returns the response when matched
     *
     * @return Route
     */
    public function options(string $pattern, $handler = null): Route
    {
        return $this->addRoute($pattern, [Router::METHOD_OPTIONS], $handler);
    }

    /**
     * Maps any request to a handler.
     *
     * @param string $pattern Matched route pattern
     * @param mixed  $handler Handler that returns the response when matched
     *
     * @return Route
     */
    public function any(string $pattern, $handler = null): Route
    {
        return $this->addRoute($pattern, Router::HTTP_METHODS_STANDARD, $handler);
    }

    /**
     * Maps any request to a resource handler and prefix class method by request method.
     * If you request on "/account" path with a GET method, prefixed by the name
     * parameter eg: 'user', class method will match `getUser`.
     *
     * @param string              $name     The prefixed name attached to request method
     * @param string              $pattern  matched path where request should be sent to
     * @param class-string|object $resource Handler that returns the response
     *
     * @return Route
     */
    public function resource(string $name, string $pattern, $resource): Route
    {
        return $this->any(sprintf('api://%s/%s', $name, $pattern), $resource);
    }

    /**
     * Find a route by name.
     *
     * @param string $name The route name
     *
     * @return null|Route A Route instance or null when not found
     */
    public function find(string $name): ?Route
    {
        /** @var Route|RouteCollection $route */
        foreach ($this->routes as $route) {
            if ($route instanceof Route && $name === $route->getName()) {
                return $route;
            }
        }

        return null;
    }

    /**
     * @param string $prefix
     * @param self   $routes
     *
     * @return Route[]
     */
    private function doMerge(string $prefix, self $routes): array
    {
        /** @var Route|RouteCollection $route */
        foreach ($this->routes as $route) {
            if ($route instanceof Route) {
                if (null === $name = $route->getName()) {
                    $name = $base = $route->generateRouteName('');
                    $i    = 0;

                    while ($routes->find($name)) {
                        $name = $base . '_' . ++$i;
                    }
                }

                $routes->add($route->bind($prefix . $name));
            } else {
                $route->doMerge($prefix . $route->namePrefix, $routes);
            }
        }

        return $routes->routes;
    }
}
