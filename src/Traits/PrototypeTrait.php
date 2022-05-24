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

namespace Flight\Routing\Traits;

/**
 * A trait providing route method prototyping.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
trait PrototypeTrait
{
    private int $defaultIndex = 0;

    /** @var array<string,mixed[]> */
    private array $prototypes = [];

    /**
     * Allows a proxied method call to route(s).
     *
     * @throws \RuntimeException if locked
     * @throws \UnexpectedValueException if bind method is called used for route
     *
     * @return $this
     */
    public function prototype(array $routeData)
    {
        foreach ($routeData as $routeMethod => $arguments) {
            $arguments = \is_array($arguments) ? $arguments : [$arguments];

            if (null === $this->route && 'bind' === $routeMethod) {
                throw new \UnexpectedValueException(\sprintf('Binding the name "%s" is only supported on routes.', $arguments[0]));
            }

            if ('s' === $routeMethod[-1]) {
                $arguments = [$arguments];
            }

            $this->doPrototype($routeMethod, $arguments);
        }

        return $this;
    }

    /**
     * This method performs two functions.
     *
     * - Unmounts a group collection to continue routes stalk.
     * - Adds a route into collection's stack.
     *
     * @return $this
     */
    public function end()
    {
        if (null !== $this->route) {
            $this->routes[] = $this->route;
            $this->route = null;

            $defaultIndex = $this->defaultIndex;
            $this->defaultIndex = 0;

            if ($defaultIndex >= 0) {
                return $this;
            }
        }

        return $this->parent ?? $this;
    }

    /**
     * Prototype a unique name to a route.
     *
     * @see Route::bind() for more information
     *
     * @throws \UnderflowException if route doesn't exist
     *
     * @return $this
     */
    public function bind(string $routeName)
    {
        if (null === $this->route) {
            throw new \UnderflowException(\sprintf('Binding the name "%s" is only supported on routes.', $routeName));
        }

        $this->route->bind($routeName);

        return $this;
    }

    /**
     * Prototype a handler to a route.
     *
     * @param mixed $to PHP class, object or callable that returns the response when matched
     *
     * @throws \UnderflowException if route doesn't exist
     *
     * @return $this
     */
    public function run($to)
    {
        if (null === $this->route) {
            throw new \UnderflowException(sprintf('Binding a handler with type of "%s", is only supported on routes.', \get_debug_type($to)));
        }

        $this->route->run($to);

        return $this;
    }

    /**
     * Prototype optional default values to route(s)
     *
     * @param mixed $default The default value
     *
     * @see Route::default() for more information
     *
     * @return $this
     */
    public function default(string $variable, $default)
    {
        return $this->doPrototype(__FUNCTION__, \func_get_args());
    }

    /**
     * Prototype optional default values to route(s)
     *
     * @param array<string,mixed> $values
     *
     * @see Route::defaults() for more information
     *
     * @return $this
     */
    public function defaults(array $values)
    {
        return $this->doPrototype(__FUNCTION__, \func_get_args());
    }

    /**
     * Prototype a rule to a named placeholder in route pattern.
     *
     * @param string|string[] $regexp The regexp to apply
     *
     * @see Route::assert() for more information
     *
     * @return $this
     */
    public function assert(string $variable, $regexp)
    {
        return $this->doPrototype(__FUNCTION__, \func_get_args());
    }

    /**
     * Prototype a set of rules to a named placeholder in route pattern.
     *
     * @param array<string,string|string[]> $regexps The regexps to apply
     *
     * @see Route::asserts() for more information
     *
     * @return $this
     */
    public function asserts(array $regexps)
    {
        return $this->doPrototype(__FUNCTION__, \func_get_args());
    }

    /**
     * Prototype the parameter supplied to route handler's constructor/factory.
     *
     * @param mixed $value The parameter value
     *
     * @see Route::argument() for more information
     *
     * @return $this
     */
    public function argument(string $parameter, $value)
    {
        return $this->doPrototype(__FUNCTION__, \func_get_args());
    }

    /**
     * Prototype the parameters supplied to route handler's constructor/factory.
     *
     * @param array<int|string> $parameters The route handler parameters
     *
     * @see Route::arguments() for more information
     *
     * @return $this
     */
    public function arguments(array $parameters)
    {
        return $this->doPrototype(__FUNCTION__, \func_get_args());
    }

    /**
     * Prototype the missing namespace for all routes handlers.
     *
     * @see Route::namespace() for more information
     *
     * @return $this
     */
    public function namespace(string $namespace)
    {
        return $this->doPrototype(__FUNCTION__, \func_get_args());
    }

    /**
     * Prototype HTTP request method(s) to all routes.
     *
     * @see Route::method() for more information
     *
     * @return $this
     */
    public function method(string ...$methods)
    {
        return $this->doPrototype(__FUNCTION__, $methods);
    }

    /**
     * Prototype HTTP host scheme(s) to route(s)
     *
     * @see Route::scheme() for more information
     *
     * @return $this
     */
    public function scheme(string ...$schemes)
    {
        return $this->doPrototype(__FUNCTION__, $schemes);
    }

    /**
     * Prototype HTTP host name(s) to route(s)
     *
     * @see Route::domain() for more information
     *
     * @return $this
     */
    public function domain(string ...$hosts)
    {
        return $this->doPrototype(__FUNCTION__, $hosts);
    }

    /**
     * Prototype prefix path to route(s)
     *
     * @see Route::prefix() for more information
     *
     * @return $this
     */
    public function prefix(string $path)
    {
        return $this->doPrototype(__FUNCTION__, \func_get_args());
    }

    /**
     * Prototype a set of named grouped middleware(s) to route(s)
     *
     * @see Route::piped() for more information
     *
     * @return $this
     */
    public function piped(string ...$to)
    {
        return $this->doPrototype(__FUNCTION__, $to);
    }

    /**
     * Prototype a custom key and value to route(s)
     *
     * @param mixed $value
     *
     * @return $this
     */
    public function set(string $key, $value)
    {
        return $this->doPrototype('setData', \func_get_args());
    }

    /**
     * @param array<int,mixed> $arguments
     *
     * @return $this
     */
    protected function doPrototype(string $routeMethod, array $arguments)
    {
        if ($this->locked) {
            throw new \RuntimeException(\sprintf('Prototyping "%s" route method failed as routes collection is frozen.', $routeMethod));
        }

        if (null !== $this->route) {
            \call_user_func_array([$this->route, $routeMethod], $arguments);
        } elseif ($this->defaultIndex > 0 || \count($routes = $this->routes) < 1) {
            $this->prototypes[$routeMethod][] = $arguments;
        } else {
            foreach ($routes as $route) {
                \call_user_func_array([$route, $routeMethod], $arguments);
            }

            foreach ($this->groups as $group) {
                \call_user_func_array([$group, $routeMethod], $arguments);
            }
        }

        return $this;
    }
}
