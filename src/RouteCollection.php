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
 * @method RouteCollection assert(string $variable, string|string[] $regexp)
 * @method RouteCollection default(string $variable, mixed $default)
 * @method RouteCollection argument(string $variable, mixed $value)
 * @method RouteCollection method(string ...$methods)
 * @method RouteCollection scheme(string ...$schemes)
 * @method RouteCollection domain(string ...$hosts)
 * @method RouteCollection prefix(string $path)
 * @method RouteCollection defaults(array $values)
 * @method RouteCollection asserts(array $patterns)
 * @method RouteCollection arguments(array $parameters)
 * @method RouteCollection namespace(string $namespace)
 * @method RouteCollection piped(string ...$to)
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
final class RouteCollection
{
    /** @var self|null */
    private $parent;

    /** @var array<string,mixed[]>|null */
    private $prototypes = [];

    /** @var array<string,bool> */
    private $prototyped = [];

    /** @var Routes\FastRoute[] */
    private $routes = [];

    /** @var self[] */
    private $groups = [];

    /** @var string */
    private $uniqueId;

    /** @var string */
    private $namedPrefix;

    /** @var bool Prevent adding routes once already used or serialized */
    private $locked = false;

    /**
     * @param string $namedPrefix The unqiue name for this group
     */
    public function __construct(string $namedPrefix = '')
    {
        $this->namedPrefix = $namedPrefix;
        $this->uniqueId = (string) \uniqid($namedPrefix);
    }

    /**
     * Nested collection and routes should be cloned.
     */
    public function __clone()
    {
        foreach ($this->routes as $offset => $route) {
            $this->routes[$offset] = clone $route;
        }
    }

    /**
     * @param string[] $arguments
     */
    public function __call(string $routeMethod, array $arguments): self
    {
        $routeMethod = \strtolower($routeMethod);

        if (isset($this->prototyped[$this->uniqueId])) {
            $this->prototypes[$routeMethod] = \array_merge($this->prototypes[$routeMethod] ?? [], $arguments);
        } else {
            foreach ($this->routes as $route) {
                \call_user_func_array([$route, $routeMethod], $arguments);
            }

            if (\array_key_exists($routeMethod, $this->prototypes ?? [])) {
                unset($this->prototypes[$routeMethod]);
            }

            foreach ($this->groups as $group) {
                \call_user_func_array([$group, $routeMethod], $arguments);
            }
        }

        return $this;
    }

    /**
     * If true, new routes cannot be added.
     */
    public function isLocked(): bool
    {
        return $this->locked;
    }

    /**
     * @return array<int,Routes\FastRoute>
     */
    public function getRoutes(): array
    {
        if ($this->locked) {
            return $this->routes;
        }

        $this->locked = true; // Freeze the routes to avoid slow down on runtime.

        if (!empty($this->groups)) {
            $this->injectGroups('', $this);
            $this->groups = []; // Unset grouping ...
        }

        return self::sortRoutes($this->routes);
    }

    /**
     * Merge a collection into base.
     */
    public function populate(self $collection, bool $asGroup = false): void
    {
        if ($asGroup) {
            $this->groups[] = $collection;
        } else {
            if (!empty($collection->groups)) {
                $collection->injectGroups($collection->namedPrefix, $collection);
            }

            foreach ($collection->routes as $route) {
                $this->routes[] = $this->injectRoute($route);
            }

            $collection->groups = $collection->routes = $collection->prototypes = [];
        }
    }

    /**
     * Add route(s) to the collection.
     *
     * This method unset all setting from default route and use new settings
     * from new the route(s). If you want the default settings to be merged
     * into routes, use `addRoute` method instead.
     *
     * @param Routes\FastRoute ...$routes
     */
    public function add(Routes\FastRoute ...$routes): self
    {
        foreach ($routes as $route) {
            $this->routes[] = $this->injectRoute($route);
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
     */
    public function addRoute(string $pattern, array $methods, $handler = null): Routes\Route
    {
        return $this->routes[] = $this->injectRoute(new Routes\Route($pattern, $methods, $handler));
    }

    /**
     * Same as addRoute method, except uses Routes\FastRoute class.
     *
     * @param string   $pattern Matched route pattern
     * @param string[] $methods Matched HTTP methods
     * @param mixed    $handler Handler that returns the response when matched
     */
    public function fastRoute(string $pattern, array $methods, $handler = null): Routes\FastRoute
    {
        return $this->routes[] = $this->injectRoute(new Routes\FastRoute($pattern, $methods, $handler));
    }

    /**
     * Mounts controllers under the given route prefix.
     *
     * @param string                   $name        The route group prefixed name
     * @param callable|RouteCollection $controllers A RouteCollection instance or a callable for defining routes
     *
     * @throws \TypeError if $controllers not instance of route collection's class.
     * @throws \RuntimeException if locked
     */
    public function group(string $name, $controllers = null): self
    {
        if ($this->locked) {
            throw new \RuntimeException('Grouping cannot be added on runtime, do add all routes before runtime.');
        }

        if (\is_callable($controllers)) {
            $routes = new static($name);
            $routes->prototypes = $this->prototypes ?? [];
            $controllers($routes);

            return $this->groups[] = $routes;
        }

        return $this->groups[] = $this->injectGroup($name, $controllers ?? new static($name));
    }

    /**
     * Allows a proxied method call to route's.
     */
    public function prototype(): self
    {
        $this->prototypes = (null !== $this->parent) ? ($this->parent->prototypes ?? []) : [];
        $this->prototyped[$this->uniqueId] = true; // Prototyping calls to routes ...

        return $this;
    }

    /**
     * Unmounts a group collection to continue routes stalk.
     */
    public function end(): self
    {
        if (isset($this->prototyped[$this->uniqueId])) {
            unset($this->prototyped[$this->uniqueId]);

            // Remove last element from stack.
            if (null !== $stack = $this->prototypes) {
                \array_pop($stack);
            }

            return $this;
        }

        return $this->parent ?? $this;
    }

    /**
     * Maps a HEAD request to a handler.
     *
     * @param string $pattern Matched route pattern
     * @param mixed  $handler Handler that returns the response when matched
     */
    public function head(string $pattern, $handler = null): Routes\Route
    {
        return $this->addRoute($pattern, [Router::METHOD_HEAD], $handler);
    }

    /**
     * Maps a GET and HEAD request to a handler.
     *
     * @param string $pattern Matched route pattern
     * @param mixed  $handler Handler that returns the response when matched
     */
    public function get(string $pattern, $handler = null): Routes\Route
    {
        return $this->addRoute($pattern, [Router::METHOD_GET, Router::METHOD_HEAD], $handler);
    }

    /**
     * Maps a POST request to a handler.
     *
     * @param string $pattern Matched route pattern
     * @param mixed  $handler Handler that returns the response when matched
     */
    public function post(string $pattern, $handler = null): Routes\Route
    {
        return $this->addRoute($pattern, [Router::METHOD_POST], $handler);
    }

    /**
     * Maps a PUT request to a handler.
     *
     * @param string $pattern Matched route pattern
     * @param mixed  $handler Handler that returns the response when matched
     */
    public function put(string $pattern, $handler = null): Routes\Route
    {
        return $this->addRoute($pattern, [Router::METHOD_PUT], $handler);
    }

    /**
     * Maps a PATCH request to a handler.
     *
     * @param string $pattern Matched route pattern
     * @param mixed  $handler Handler that returns the response when matched
     */
    public function patch(string $pattern, $handler = null): Routes\Route
    {
        return $this->addRoute($pattern, [Router::METHOD_PATCH], $handler);
    }

    /**
     * Maps a DELETE request to a handler.
     *
     * @param string $pattern Matched route pattern
     * @param mixed  $handler Handler that returns the response when matched
     */
    public function delete(string $pattern, $handler = null): Routes\Route
    {
        return $this->addRoute($pattern, [Router::METHOD_DELETE], $handler);
    }

    /**
     * Maps a OPTIONS request to a handler.
     *
     * @param string $pattern Matched route pattern
     * @param mixed  $handler Handler that returns the response when matched
     */
    public function options(string $pattern, $handler = null): Routes\Route
    {
        return $this->addRoute($pattern, [Router::METHOD_OPTIONS], $handler);
    }

    /**
     * Maps any request to a handler.
     *
     * @param string $pattern Matched route pattern
     * @param mixed  $handler Handler that returns the response when matched
     */
    public function any(string $pattern, $handler = null): Routes\Route
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
     */
    public function resource(string $pattern, $resource, string $action = 'action'): Routes\Route
    {
        return $this->any($pattern, new Handlers\ResourceHandler($resource, $action));
    }

    /**
     * Rearranges routes, sorting static paths before dynamic paths.
     *
     * @param Routes\FastRoute[] $routes
     *
     * @return Routes\FastRoute[]
     */
    private static function sortRoutes(array $routes): array
    {
        $sortRegex = '#^[\w+' . \implode('\\', Routes\Route::URL_PREFIX_SLASHES) . ']+$#';

        \usort($routes, static function (Routes\FastRoute $a, Routes\FastRoute $b) use ($sortRegex): int {
            $aRegex = \preg_match($sortRegex, $a->get('path'));
            $bRegex = \preg_match($sortRegex, $b->get('path'));

            return $aRegex == $bRegex ? 0 : ($aRegex < $bRegex ? +1 : -1);
        });

        return $routes;
    }

    /**
     * @throws \RuntimeException if locked
     */
    private function injectRoute(Routes\FastRoute $route): Routes\FastRoute
    {
        if ($this->locked) {
            throw new \RuntimeException('Routes cannot be added on runtime, do add all routes before runtime.');
        }

        foreach ($this->prototypes ?? [] as $routeMethod => $arguments) {
            if (empty($arguments)) {
                continue;
            }

            \call_user_func_array([$route, $routeMethod], 'prefix' === $routeMethod ? [\implode('', $arguments)] : $arguments);
        }

        if (null !== $this->parent) {
            $route->belong($this); // Attach grouping to route.
        }

        return $route;
    }

    private function injectGroup(string $prefix, self $controllers): self
    {
        $controllers->prototypes = $this->prototypes ?? [];
        $controllers->parent = $this;

        if (empty($controllers->namedPrefix)) {
            $controllers->namedPrefix = $prefix;
        }

        return $controllers;
    }

    private function injectGroups(string $prefix, self $collection): void
    {
        $unnamedRoutes = [];

        foreach ($this->groups as $group) {
            foreach ($group->routes as $route) {
                if (empty($name = $route->get('name'))) {
                    $name = $route->generateRouteName('');

                    if (isset($unnamedRoutes[$name])) {
                        $name .= ('_' !== $name[-1] ? '_' : '') . ++$unnamedRoutes[$name];
                    } else {
                        $unnamedRoutes[$name] = 0;
                    }
                }

                $collection->routes[] = $route->bind($prefix . $group->namedPrefix . $name);
            }

            if (!empty($group->groups)) {
                $group->injectGroups($prefix . $group->namedPrefix, $collection);
            }
        }
    }
}
