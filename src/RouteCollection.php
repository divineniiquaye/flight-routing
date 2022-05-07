<?php

declare(strict_types=1);

/*
 * This file is part of Flight Routing.
 *
 * PHP version 7.4 and above required
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
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class RouteCollection
{
    use Traits\PrototypeTrait;

    private ?string $namedPrefix;
    private ?Route $route = null;
    private ?self $parent = null;
    private bool $sortRoutes, $locked = false;

    /** @var array<int,Route> */
    private array $routes = [];

    /** @var array<int,self> */
    private array $groups = [];

    /**
     * @param string $namedPrefix The unqiue name for this group
     */
    public function __construct(string $namedPrefix = null)
    {
        $this->namedPrefix = $namedPrefix;
    }

    /**
     * Nested collection and routes should be cloned.
     */
    public function __clone()
    {
        $this->includeRoute(); // Incase of missing end method call on route.

        foreach ($this->routes as $offset => $route) {
            $this->routes[$offset] = clone $route;
        }
    }

    /**
     * Create an instance of resolved routes.
     *
     * @return static
     */
    final public static function create(array $routes, bool $locked = true)
    {
        $collection = new static();
        $collection->routes = $routes;
        $collection->locked = $locked;

        return $collection;
    }

    /**
     * Inject Groups and sort routes in a natural order.
     */
    final public function buildRoutes(bool $lock = true): void
    {
        $this->includeRoute(); // Incase of missing end method call on route.
        $routes = $this->routes;

        if (!empty($this->groups)) {
            $this->injectGroups('', $routes);
        }

        \usort($routes, static function (Route $a, Route $b): int {
            return !$a->getStaticPrefix() <=> !$b->getStaticPrefix() ?: \strnatcmp($a->getPath(), $b->getPath());
        });

        $this->locked = $lock; // Lock grouping and prototyping
        $this->routes = $routes;
    }

    /**
     * Get all the routes.
     *
     * @return array<int,Route>
     */
    public function getRoutes(): array
    {
        if (!$this->locked) {
            $this->buildRoutes();
        }

        return $this->routes;
    }

    /**
     * Get the current route in stack.
     */
    public function getRoute(): ?Route
    {
        return $this->route;
    }

    /**
     * Add route to the collection.
     *
     * @return $this
     */
    public function add(Route $route)
    {
        $this->route = $this->injectRoute($route);

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
     * @return $this
     */
    public function addRoute(string $pattern, array $methods, $handler = null)
    {
        $this->route = $this->injectRoute(new Route($pattern, $methods, $handler));

        return $this;
    }

    /**
     * Add routes to the collection.
     *
     * @param Route[] $routes
     *
     * @throws \TypeError        if $routes doesn't contain a route instance
     * @throws \RuntimeException if locked
     *
     * @return $this
     */
    public function routes(array $routes)
    {
        foreach ($routes as $route) {
            $this->routes[] = $this->injectRoute($route);
        }

        return $this;
    }

    /**
     * Mounts controllers under the given route prefix.
     *
     * @param string|null                   $name        The route group prefixed name
     * @param callable|RouteCollection|null $controllers A RouteCollection instance or a callable for defining routes
     *
     * @throws \TypeError        if $controllers not instance of route collection's class
     * @throws \RuntimeException if locked
     *
     * @return $this
     */
    public function group(string $name = null, $controllers = null)
    {
        if (\is_callable($controllers)) {
            $controllers($routes = $this->injectGroup($name, new static($name)));
        }

        return $this->groups[] = $routes ?? $this->injectGroup($name, $controllers ?? new static($name));
    }

    /**
     * Merge a collection into base.
     *
     * @throws \RuntimeException if locked
     */
    public function populate(self $collection, bool $asGroup = false): void
    {
        if ($asGroup) {
            $this->groups[] = $this->injectGroup($collection->namedPrefix, $collection);
        } else {
            $this->includeRoute($collection); // Incase of missing end method call on route.
            $routes = $collection->routes;

            if (!empty($collection->groups)) {
                $collection->injectGroups($collection->namedPrefix ?? '', $routes);
            }

            foreach ($routes as $route) {
                $this->routes[] = $this->injectRoute($route);
            }
        }
    }

    /**
     * Maps a HEAD request to a handler.
     *
     * @param string $pattern Matched route pattern
     * @param mixed  $handler Handler that returns the response when matched
     *
     * @return $this
     */
    public function head(string $pattern, $handler = null)
    {
        return $this->addRoute($pattern, [Router::METHOD_HEAD], $handler);
    }

    /**
     * Maps a GET and HEAD request to a handler.
     *
     * @param string $pattern Matched route pattern
     * @param mixed  $handler Handler that returns the response when matched
     *
     * @return $this
     */
    public function get(string $pattern, $handler = null)
    {
        return $this->addRoute($pattern, [Router::METHOD_GET, Router::METHOD_HEAD], $handler);
    }

    /**
     * Maps a POST request to a handler.
     *
     * @param string $pattern Matched route pattern
     * @param mixed  $handler Handler that returns the response when matched
     *
     * @return $this
     */
    public function post(string $pattern, $handler = null)
    {
        return $this->addRoute($pattern, [Router::METHOD_POST], $handler);
    }

    /**
     * Maps a PUT request to a handler.
     *
     * @param string $pattern Matched route pattern
     * @param mixed  $handler Handler that returns the response when matched
     *
     * @return $this
     */
    public function put(string $pattern, $handler = null)
    {
        return $this->addRoute($pattern, [Router::METHOD_PUT], $handler);
    }

    /**
     * Maps a PATCH request to a handler.
     *
     * @param string $pattern Matched route pattern
     * @param mixed  $handler Handler that returns the response when matched
     *
     * @return $this
     */
    public function patch(string $pattern, $handler = null)
    {
        return $this->addRoute($pattern, [Router::METHOD_PATCH], $handler);
    }

    /**
     * Maps a DELETE request to a handler.
     *
     * @param string $pattern Matched route pattern
     * @param mixed  $handler Handler that returns the response when matched
     *
     * @return $this
     */
    public function delete(string $pattern, $handler = null)
    {
        return $this->addRoute($pattern, [Router::METHOD_DELETE], $handler);
    }

    /**
     * Maps a OPTIONS request to a handler.
     *
     * @param string $pattern Matched route pattern
     * @param mixed  $handler Handler that returns the response when matched
     *
     * @return $this
     */
    public function options(string $pattern, $handler = null)
    {
        return $this->addRoute($pattern, [Router::METHOD_OPTIONS], $handler);
    }

    /**
     * Maps any request to a handler.
     *
     * @param string $pattern Matched route pattern
     * @param mixed  $handler Handler that returns the response when matched
     *
     * @return $this
     */
    public function any(string $pattern, $handler = null)
    {
        return $this->addRoute($pattern, Router::HTTP_METHODS_STANDARD, $handler);
    }

    /**
     * Maps any Router::HTTP_METHODS_STANDARD request to a resource handler prefixed to $action's method name.
     *
     * E.g: Having pattern as "/accounts/{userId}", all request made from supported request methods
     * are to have the same url.
     *
     * @param string              $action   The prefixed name attached to request method
     * @param string              $pattern  matched path where request should be sent to
     * @param class-string|object $resource Handler that returns the response
     *
     * @return $this
     */
    public function resource(string $pattern, $resource, string $action = 'action')
    {
        return $this->any($pattern, new Handlers\ResourceHandler($resource, $action));
    }

    /**
     * @throws \RuntimeException if locked
     */
    protected function injectRoute(Route $route): Route
    {
        if ($this->locked) {
            throw new \RuntimeException('Cannot add a route to a frozen routes collection.');
        }

        $this->includeRoute(); // Incase of missing end method call on route.

        if (!empty($defaultsStack = $this->prototypes)) {
            foreach ($defaultsStack as $routeMethod => $arguments) {
                if ('prefix' === $routeMethod) {
                    $route->prefix(\implode('', \array_merge(...$arguments)));

                    continue;
                }

                if (\count($arguments) > 1) {
                    foreach ($arguments as $argument) {
                        $route->{$routeMethod}(...$argument);
                    }

                    continue;
                }

                $route->{$routeMethod}(...$arguments[0]);
            }
        }

        return $route;
    }

    /**
     * Include route to stack if not done.
     */
    protected function includeRoute(self $collection = null): void
    {
        if (null !== $this->route) {
            $this->defaultIndex = -1;

            $this->routes[] = $this->route; // Incase an end method is missing at the end of a route call.
            $this->route = null;
        }

        if (null !== $collection) {
            $collection->includeRoute();

            if (empty($collection->routes)) {
                $collection->defaultIndex = 1;
            }

            $collection->prototypes = \array_merge($this->prototypes, $collection->prototypes);
            $collection->parent = $this;
        }
    }

    /**
     * @return self|$this
     */
    protected function injectGroup(?string $prefix, self $controllers)
    {
        if ($this->locked) {
            throw new \RuntimeException('Cannot add a nested routes collection to a frozen routes collection.');
        }

        if ($controllers->sortRoutes) {
            throw new \RuntimeException('Cannot sort routes in a nested collection.');
        }

        $this->includeRoute($controllers); // Incase of missing end method call on route.

        if (empty($controllers->namedPrefix)) {
            $controllers->namedPrefix = $prefix;
        }

        return $controllers;
    }

    /**
     * @param array<int,Route> $collection
     */
    private function injectGroups(string $prefix, array &$collection): void
    {
        $unnamedRoutes = [];

        foreach ($this->groups as $group) {
            $group->includeRoute(); // Incase of missing end method call on route.

            foreach ($group->routes as $route) {
                if (empty($name = $route->getName())) {
                    $name = $route->generateRouteName('');

                    if (isset($unnamedRoutes[$name])) {
                        $name .= ('_' !== $name[-1] ? '_' : '') . ++$unnamedRoutes[$name];
                    } else {
                        $unnamedRoutes[$name] = 0;
                    }
                }

                $collection[] = $route->bind($prefix . $group->namedPrefix . $name);
            }

            if (!empty($group->groups)) {
                $group->injectGroups($prefix . $group->namedPrefix, $collection);
            }
        }

        $this->groups = [];
    }
}
