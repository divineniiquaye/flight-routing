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

namespace Flight\Routing\Routes;

use Flight\Routing\Exceptions\InvalidControllerException;
use Flight\Routing\Handlers\ResourceHandler;

/**
 * Value object representing a single route.
 *
 * Route path and prefixing a path are not casted. This class is meant to be
 * extendable for addition support on route(s).
 *
 * The default support for this route class:
 * - name binding
 * - methods binding
 * - handler & namespacing
 * - arguments binding to handler
 * - pattern placeholders assert binding
 * - add defaults binding
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class FastRoute extends AbstractRoute
{
    /**
     * Sets the route path prefix.
     *
     * @return static
     */
    public function prefix(string $path)
    {
        $this->data['path'] = $path . $this->data['path'] ?? '';

        return $this;
    }

    /**
     * Sets the route path pattern.
     *
     * @return static
     */
    public function path(string $pattern)
    {
        $this->data['path'] = $pattern;

        return $this;
    }

    /**
     * Sets the requirement for the HTTP method.
     *
     * @param string $methods the HTTP method(s) name
     *
     * @return static
     */
    public function method(string ...$methods)
    {
        foreach ($methods as $method) {
            $this->data['methods'][\strtoupper($method)] = true;
        }

        return $this;
    }

    /**
     * Sets the route name.
     *
     * @return static
     */
    public function bind(string $routeName)
    {
        $this->data['name'] = $routeName;

        return $this;
    }

    /**
     * Sets the parameter value for a route handler.
     *
     * @param mixed $value The parameter value
     *
     * @return static
     */
    public function argument(string $parameter, $value)
    {
        if (\is_numeric($value)) {
            $value = (int) $value;
        } elseif (\is_string($value)) {
            $value = \rawurldecode($value);
        }

        $this->data['arguments'][$parameter] = $value;

        return $this;
    }

    /**
     * Sets the parameter values for a route handler.
     *
     * @param array<int|string> $parameters The route handler parameters
     *
     * @return static
     */
    public function arguments(array $parameters)
    {
        foreach ($parameters as $variable => $value) {
            $this->argument($variable, $value);
        }

        return $this;
    }

    /**
     * Sets the route code that should be executed when matched.
     *
     * @param mixed $to PHP class, object or callable that returns the response when matched
     *
     * @return static
     */
    public function run($to)
    {
        $this->data['handler'] = $to;

        return $this;
    }

    /**
     * Sets the missing namespace on route's handler.
     *
     * @throws InvalidControllerException if $namespace is invalid
     *
     * @return static
     */
    public function namespace(string $namespace)
    {
        if ('' !== $namespace) {
            if ('\\' === $namespace[-1]) {
                throw new InvalidControllerException(\sprintf('Namespace "%s" provided for routes must not end with a "\\".', $namespace));
            }

            if (isset($this->data['handler'])) {
                $this->data['handler'] = self::resolveNamespace($namespace, $this->data['handler']);
            }
        }

        return $this;
    }

    /**
     * Attach a named middleware group(s) to route.
     *
     * @return static
     */
    public function piped(string ...$to)
    {
        foreach ($to as $namedMiddleware) {
            $this->middlewares[] = $namedMiddleware;
        }

        return $this;
    }

    /**
     * Sets the requirement for a route variable.
     *
     * @param string|string[] $regexp The regexp to apply
     *
     * @return static
     */
    public function assert(string $variable, $regexp)
    {
        $this->data['patterns'][$variable] = $regexp;

        return $this;
    }

    /**
     * Sets the requirements for a route variable.
     *
     * @param array<string,string|string[]> $regexps The regexps to apply
     *
     * @return static
     */
    public function asserts(array $regexps)
    {
        foreach ($regexps as $variable => $regexp) {
            $this->assert($variable, $regexp);
        }

        return $this;
    }

    /**
     * Sets the default value for a route variable.
     *
     * @param mixed $default The default value
     *
     * @return static
     */
    public function default(string $variable, $default)
    {
        $this->data['defaults'][$variable] = $default;

        return $this;
    }

    /**
     * Sets the default values for a route variables.
     *
     * @param array<string,mixed> $values
     *
     * @return static
     */
    public function defaults(array $values)
    {
        foreach ($values as $variable => $default) {
            $this->default($variable, $default);
        }

        return $this;
    }

    /**
     * @internal skip throwing an exception and return existing $controller
     *
     * @param callable|object|string|string[] $controller
     *
     * @return mixed
     */
    private static function resolveNamespace(string $namespace, $controller)
    {
        if ($controller instanceof ResourceHandler) {
            return $controller->namespace($namespace);
        }

        if (\is_string($controller) && (!\str_starts_with($controller, $namespace) && '\\' === $controller[0])) {
            return $namespace . $controller;
        }

        if ((\is_array($controller) && \array_keys($controller) === [0, 1]) && \is_string($controller[0])) {
            $controller[0] = self::resolveNamespace($namespace, $controller[0]);
        }

        return $controller;
    }
}
