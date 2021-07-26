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

use Flight\Routing\Interfaces\{RouteCompilerInterface, RouteMapInterface};

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
 * @method RouteCollection withAssert(string $variable, string|string[] $regexp)
 * @method RouteCollection withDefault(string $variable, mixed $default)
 * @method RouteCollection withArgument(string $variable, mixed $value)
 * @method RouteCollection withMethod(string ...$methods)
 * @method RouteCollection withScheme(string ...$schemes)
 * @method RouteCollection withDomain(string ...$hosts)
 * @method RouteCollection withPrefix(string $path)
 * @method RouteCollection withDefaults(array $values)
 * @method RouteCollection withAsserts(array $patterns)
 * @method RouteCollection withArguments(array $parameters)
 * @method RouteCollection withNamespace(string $namespace)
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class RouteCollection extends \ArrayIterator implements RouteMapInterface
{
    use Traits\GroupingTrait;

    /**
     * Prototype methods for routes grouping.
     */
    protected const SUPPORTED_GETTER_METHODS = [
        'withNamespace' => 'namespace',
        'withMethod' => 'method',
        'withScheme' => 'scheme',
        'withDomain' => 'domain',
        'withPrefix' => 'prefix',
        'withAssert' => 'assert',
        'withAsserts' => 'asserts',
        'withDefault' => 'default',
        'withDefaults' => 'defaults',
        'withArgument' => 'argument',
        'withArguments' => 'arguments',
        'withMiddleware' => 'middleware',
    ];

    public function __construct(RouteCompilerInterface $compiler = null)
    {
        $this->compiler = $compiler ?? new RouteCompiler();
        parent::__construct();
    }

    /**
     * @param string   $method
     * @param string[] $arguments
     *
     * @return mixed
     */
    public function __call($method, $arguments)
    {
        if (null === $routeMethod = self::SUPPORTED_GETTER_METHODS[$method] ?? null) {
            throw new \BadMethodCallException(\sprintf('Method "%s" not found in %s class.', $method, __CLASS__));
        }

        if (null !== $stack = $this->stack) {
            $this->stack[$routeMethod] = \array_merge($stack[$routeMethod] ?? [], $arguments);
        } elseif ($this->offsetExists('routes')) {
            foreach ($this->offsetGet('routes') as $route) {
                \call_user_func_array([$route, $routeMethod], $arguments);
            }
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getCompiler(): RouteCompilerInterface
    {
        return $this->compiler;
    }

    /**
     * {@inheritdoc}
     */
    public function getData(): RouteMapInterface
    {
        if (0 === $this->countRoutes) {
            goto collection;
        }

        if ($this->offsetExists('group')) {
            $this->doMerge('', $this);
            $this->offsetUnset('group'); // Unset grouping ...
        }

        if ($this->offsetExists('dynamicRoutesMap')) {
            $routeMapToRegexps = [];

            foreach (\array_chunk($this['dynamicRoutesMap'][0], 100, true) as $dynamicRoute) {
                $routeMapToRegexps[] = '~^(?|' . \implode('|', $dynamicRoute) . ')$~u';
            }

            $this['dynamicRoutesMap'][0] = $routeMapToRegexps;
        }

        $this->parent = $this->stack = null;
        $this->countRoutes = 0;

        collection: // Instead of an array, return itself.
        return $this;
    }

    /**
     * Add route(s) to the collection.
     *
     * This method unset all setting from default route and use new settings
     * from new the route(s). If you want the default settings to be merged
     * into routes, use `addRoute` method instead.
     *
     * @param Route ...$routes
     */
    public function add(Route ...$routes): self
    {
        foreach ($routes as $route) {
            $this['routes'][] = $this->resolveWith($route);
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
    public function addRoute(string $pattern, array $methods, $handler = null): Route
    {
        return $this['routes'][] = $this->resolveWith(new Route($pattern, $methods, $handler));
    }

    /**
     * Mounts controllers under the given route prefix.
     *
     * @param string                   $name        The route group prefixed name
     * @param callable|RouteCollection $controllers A RouteCollection instance or a callable for defining routes
     *
     * @throws \LogicException
     */
    public function group(string $name, $controllers = null): self
    {
        if (null === $controllers) {
            $controllers = new static($this->compiler);
            $controllers->stack = $this->stack ?? [];
        } elseif (\is_callable($controllers)) {
            $controllers($controllers = new static($this->compiler));
        } elseif (!$controllers instanceof self) {
            throw new \LogicException(\sprintf('The %s() method takes either a "%s" instance or a callable of its self.', __METHOD__, __CLASS__));
        }

        if (!empty($name)) {
            $this['group'][$name] = $controllers;
        } else {
            $this['group'][] = $controllers;
        }

        $controllers->parent = $this;

        return $controllers;
    }

    /**
     * Unmounts a group collection to continue routes stalk.
     */
    public function end(): self
    {
        // Remove last element from stack.
        if (null !== $stack = $this->stack) {
            unset($stack[\count($stack) - 1]);
        }

        return $this->parent ?? $this;
    }

    /**
     * Maps a HEAD request to a handler.
     *
     * @param string $pattern Matched route pattern
     * @param mixed  $handler Handler that returns the response when matched
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
     */
    public function any(string $pattern, $handler = null): Route
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
    public function resource(string $pattern, $resource, string $action = 'action'): Route
    {
        return $this->any($pattern, new Handlers\ResourceHandler($resource, $action));
    }

    /**
     * Find a route by named route.
     *
     * @param string $name The route name
     *
     * @return Route|null A Route instance or null when not found
     */
    public function find(string $name): ?Route
    {
        foreach ($this->routes as $route) {
            if ($route instanceof Route && $name === $route->get('name')) {
                return $route;
            }
        }

        return null;
    }
}
