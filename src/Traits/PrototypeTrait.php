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
    private ?string $uniqueId;

    /** @var array<string,mixed[]>|null */
    private ?array $prototypes = [];

    /** @var array<string,bool> */
    private array $prototyped = [];

    /**
     * Allows a proxied method call to route's.
     *
     * @throws \RuntimeException if locked
     *
     * @return $this
     */
    public function prototype()
    {
        if (null === $uniqueId = $this->uniqueId) {
            throw new \RuntimeException('Routes method prototyping must be done before calling the getRoutes() method.');
        }

        $this->prototypes = (null !== $this->parent) ? $this->parent->prototypes : [];
        $this->prototyped[$uniqueId] = true; // Prototyping calls to routes ...

        return $this;
    }

    /**
     * Unmounts a group collection to continue routes stalk.
     *
     * @return \Flight\Routing\RouteCollection
     */
    public function end()
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
     * Prototype the optional default value which maybe required by routes.
     *
     * @param mixed $default The default value
     *
     * @see Route::default() for more information
     *
     * @return $this
     */
    public function default(string $variable, $default)
    {
        return $this->doPrototype('default', \func_get_args());
    }

    /**
     * Prototype the optional default values which maybe required by routes.
     *
     * @param array<string,mixed> $values
     *
     * @see Route::defaults() for more information
     *
     * @return $this
     */
    public function defaults(array $values)
    {
        return $this->doPrototype('defaults', \func_get_args());
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
        return $this->doPrototype('assert', \func_get_args());
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
        return $this->doPrototype('asserts', \func_get_args());
    }

    /**
     * Prototype the arguments supplied to route handler's constructor/factory.
     *
     * @param mixed $value The parameter value
     *
     * @see Route::argument() for more information
     *
     * @return $this
     */
    public function argument(string $parameter, $value)
    {
        return $this->doPrototype('argument', \func_get_args());
    }

    /**
     * Prototype the arguments supplied to route handler's constructor/factory.
     *
     * @param array<int|string> $parameters The route handler parameters
     *
     * @see Route::arguments() for more information
     *
     * @return $this
     */
    public function arguments(array $parameters)
    {
        return $this->doPrototype('arguments', \func_get_args());
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
        return $this->doPrototype('namespace', \func_get_args());
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
        return $this->doPrototype('method', \func_get_args());
    }

    /**
     * Prototype HTTP host scheme(s) to all routes.
     *
     * @see Route::scheme() for more information
     *
     * @return $this
     */
    public function scheme(string ...$schemes)
    {
        return $this->doPrototype('scheme', \func_get_args());
    }

    /**
     * Prototype HTTP host scheme(s) to all routes.
     *
     * @see Route::scheme() for more information
     *
     * @return $this
     */
    public function domain(string ...$hosts)
    {
        return $this->doPrototype('domain', \func_get_args());
    }

    /**
     * Prototype a prefix prepended to route's path.
     *
     * @see Route::prefix() for more information
     *
     * @return $this
     */
    public function prefix(string $path)
    {
        return $this->doPrototype('prefix', \func_get_args());
    }

    /**
     * Prototype named middleware group(s) to all routes.
     *
     * @see Route::piped() for more information
     *
     * @return $this
     */
    public function piped(string ...$to)
    {
        return $this->doPrototype('piped', \func_get_args());
    }

    /**
     * @param array<int,mixed> $arguments
     *
     * @return $this
     */
    protected function doPrototype(string $routeMethod, array $arguments)
    {
        if (isset($this->prototyped[$this->uniqueId])) {
            $this->prototypes[$routeMethod] = \array_merge($this->prototypes[$routeMethod] ?? [], $arguments);
        } else {
            foreach ($this->routes as $route) {
                \call_user_func_array([$route, $routeMethod], $arguments);
            }

            foreach ($this->groups as $group) {
                \call_user_func_array([$group, $routeMethod], $arguments);
            }

            if (\array_key_exists($routeMethod, $this->prototypes ?? [])) {
                unset($this->prototypes[$routeMethod]);
            }
        }

        return $this;
    }
}
