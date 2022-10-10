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
 * - get
 * - post
 * - put
 * - patch
 * - delete
 * - options
 * - any
 * - resource
 *
 * A general `add()` method allows specifying multiple request methods and/or
 * arbitrary request methods when creating a path-based route.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class RouteCollection implements \Countable, \ArrayAccess
{
    use Traits\PrototypeTrait;

    /**
     * A Pattern to Locates appropriate route by name, support dynamic route allocation using following pattern:
     * Pattern route:   `pattern/*<controller@action>`
     * Default route: `*<controller@action>`
     * Only action:   `pattern/*<action>`.
     */
    public const RCA_PATTERN = '/^(?:([a-z]+)\:)?(?:\/{2}([^\/]+))?([^*]*)(?:\*\<(?:([\w+\\\\]+)\@)?(\w+)\>)?$/u';

    /**
     * A Pattern to match the route's priority.
     *
     * If route path matches, 1 is expected return else 0 should be return as priority index.
     */
    protected const PRIORITY_REGEX = '/([^<[{:]+\b)/A';

    protected ?self $parent = null;
    protected ?string $namedPrefix = null;

    /**
     * @internal
     *
     * @param array<string,mixed> $properties
     */
    public static function __set_state(array $properties): static
    {
        $collection = new static();

        foreach ($properties as $property => $value) {
            $collection->{$property} = $value;
        }

        return $collection;
    }

    /**
     * Sort all routes beginning with static routes.
     */
    public function sort(): void
    {
        if (!empty($this->groups)) {
            $this->injectGroups('', $this->routes, $this->defaultIndex);
        }

        $this->sorted || $this->sorted = \usort($this->routes, static function (array $a, array $b): int {
            $ap = $a['prefix'] ?? null;
            $bp = $b['prefix'] ?? null;

            return !($ap && $ap === $a['path']) <=> !($bp && $bp === $b['path']) ?: \strnatcmp($a['path'], $b['path']);
        });
    }

    /**
     * Get all the routes.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getRoutes(): array
    {
        if (!empty($this->groups)) {
            $this->injectGroups('', $this->routes, $this->defaultIndex);
        }

        return $this->routes;
    }

    /**
     * Get the total number of routes.
     */
    public function count(): int
    {
        if (!empty($this->groups)) {
            $this->injectGroups('', $this->routes, $this->defaultIndex);
        }

        return $this->defaultIndex + 1;
    }

    /**
     * Checks if route by its index exists.
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->routes[$offset]);
    }

    /**
     * Get the route by its index.
     *
     * @return null|array<string,mixed>
     */
    public function offsetGet(mixed $offset): ?array
    {
        return $this->routes[$offset] ?? null;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->routes[$offset]);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \BadMethodCallException('The operator "[]" for new route, use the add() method instead.');
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
    public function add(string $pattern, array $methods = Router::DEFAULT_METHODS, mixed $handler = null): self
    {
        $this->asRoute = true;
        $this->routes[++$this->defaultIndex] = ['handler' => $handler];
        $this->path($pattern);

        foreach ($this->prototypes as $route => $arguments) {
            if ('prefix' === $route) {
                $this->prefix(\implode('', $arguments));
            } elseif ('domain' === $route) {
                $this->domain(...$arguments);
            } elseif ('namespace' === $route) {
                foreach ($arguments as $namespace) {
                    $this->namespace($namespace);
                }
            } else {
                $this->routes[$this->defaultIndex][$route] = $arguments;
            }
        }

        foreach ($methods as $method) {
            $this->routes[$this->defaultIndex]['methods'][\strtoupper($method)] = true;
        }

        return $this;
    }

    /**
     * Mounts controllers under the given route prefix.
     *
     * @param null|string                   $name        The route group prefixed name
     * @param null|callable|RouteCollection $collection A RouteCollection instance or a callable for defining routes
     * @param bool $return If true returns a new collection instance else returns $this
     */
    public function group(string $name = null, callable|self $collection = null, bool $return = false): self
    {
        $this->asRoute = false;

        if (\is_callable($collection)) {
            $collection($routes = $this->injectGroup($name, new static()), $return);
        }
        $route = $routes ?? $this->injectGroup($name, $collection ?? new static(), $return);
        $this->groups[] = $route;

        return $return ? $route : $this;
    }

    /**
     * Merge a collection into base.
     *
     * @return $this
     */
    public function populate(self $collection, bool $asGroup = false)
    {
        if ($asGroup) {
            $this->groups[] = $this->injectGroup($collection->namedPrefix, $collection);
        } else {
            $routes = $collection->routes;
            $asRoute = $this->asRoute;

            if (!empty($collection->groups)) {
                $collection->injectGroups($collection->namedPrefix ?? '', $routes, $this->defaultIndex);
            }

            foreach ($routes as $route) {
                $this->add($route['path'], [], $route['handler']);
                $this->routes[$this->defaultIndex] = \array_merge_recursive(
                    $this->routes[$this->defaultIndex],
                    \array_diff_key($route, ['path' => null, 'handler' => null, 'prefix' => null])
                );
            }
            $this->asRoute = $asRoute;
        }

        return $this;
    }

    /**
     * Maps a GET and HEAD request to a handler.
     *
     * @param string $pattern Matched route pattern
     * @param mixed  $handler Handler that returns the response when matched
     *
     * @return $this
     */
    public function get(string $pattern, $handler = null): self
    {
        return $this->add($pattern, [Router::METHOD_GET, Router::METHOD_HEAD], $handler);
    }

    /**
     * Maps a POST request to a handler.
     *
     * @param string $pattern Matched route pattern
     * @param mixed  $handler Handler that returns the response when matched
     *
     * @return $this
     */
    public function post(string $pattern, $handler = null): self
    {
        return $this->add($pattern, [Router::METHOD_POST], $handler);
    }

    /**
     * Maps a PUT request to a handler.
     *
     * @param string $pattern Matched route pattern
     * @param mixed  $handler Handler that returns the response when matched
     *
     * @return $this
     */
    public function put(string $pattern, $handler = null): self
    {
        return $this->add($pattern, [Router::METHOD_PUT], $handler);
    }

    /**
     * Maps a PATCH request to a handler.
     *
     * @param string $pattern Matched route pattern
     * @param mixed  $handler Handler that returns the response when matched
     *
     * @return $this
     */
    public function patch(string $pattern, $handler = null): self
    {
        return $this->add($pattern, [Router::METHOD_PATCH], $handler);
    }

    /**
     * Maps a DELETE request to a handler.
     *
     * @param string $pattern Matched route pattern
     * @param mixed  $handler Handler that returns the response when matched
     *
     * @return $this
     */
    public function delete(string $pattern, $handler = null): self
    {
        return $this->add($pattern, [Router::METHOD_DELETE], $handler);
    }

    /**
     * Maps a OPTIONS request to a handler.
     *
     * @param string $pattern Matched route pattern
     * @param mixed  $handler Handler that returns the response when matched
     *
     * @return $this
     */
    public function options(string $pattern, $handler = null): self
    {
        return $this->add($pattern, [Router::METHOD_OPTIONS], $handler);
    }

    /**
     * Maps any request to a handler.
     *
     * @param string $pattern Matched route pattern
     * @param mixed  $handler Handler that returns the response when matched
     *
     * @return $this
     */
    public function any(string $pattern, $handler = null): self
    {
        return $this->add($pattern, Router::HTTP_METHODS_STANDARD, $handler);
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
    public function resource(string $pattern, string|object $resource, string $action = 'action'): self
    {
        return $this->any($pattern, new Handlers\ResourceHandler($resource, $action));
    }

    public function generateRouteName(string $prefix, array $route = null): string
    {
        $route = $route ?? $this->routes[$this->defaultIndex];
        $routeName = \implode('_', \array_keys($route['methods'] ?? [])).'_'.$prefix.$route['path'] ?? '';
        $routeName = \str_replace(['/', ':', '|', '-'], '_', $routeName);
        $routeName = (string) \preg_replace('/[^a-z0-9A-Z_.]+/', '', $routeName);

        return (string) \preg_replace(['/\_+/', '/\.+/'], ['_', '.'], $routeName);
    }

    // 'next', 'key', 'valid', 'rewind'

    protected function injectGroup(?string $prefix, self $controllers, bool $return = false): self
    {
        $controllers->prototypes = \array_merge_recursive($this->prototypes, $controllers->prototypes);

        if ($return) {
            $controllers->parent = $this;
        }

        if (empty($controllers->namedPrefix)) {
            $controllers->namedPrefix = $prefix;
        }

        return $controllers;
    }

    /**
     * @param array<int,array<string,mixed>> $collection
     */
    private function injectGroups(string $prefix, array &$collection, int &$count): void
    {
        $unnamedRoutes = [];

        foreach ($this->groups as $group) {
            foreach ($group->routes as $route) {
                if (empty($name = $route['name'] ?? '')) {
                    $name = $group->generateRouteName('', $route);

                    if (isset($unnamedRoutes[$name])) {
                        $name .= ('_' !== $name[-1] ? '_' : '').++$unnamedRoutes[$name];
                    } else {
                        $unnamedRoutes[$name] = 0;
                    }
                }

                $route['name'] = $prefix.$group->namedPrefix.$name;
                $collection[] = $route;
                ++$count;
            }

            if (!empty($group->groups)) {
                $group->injectGroups($prefix.$group->namedPrefix, $collection, $count);
            }
        }

        $this->groups = [];
    }
}
