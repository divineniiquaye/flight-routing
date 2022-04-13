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

use Flight\Routing\Exceptions\{InvalidControllerException, UriHandlerException};
use Flight\Routing\Route;
use Flight\Routing\Handlers\ResourceHandler;

trait DataTrait
{
    /** @var array<string,mixed> */
    protected array $data;

    /**
     * Sets the route path prefix.
     *
     * @return $this
     */
    public function prefix(string $path)
    {
        if (!empty($path)) {
            $uri = $this->data['path'] ?? '/';

            if (\strlen($uri) > 1 && isset(Route::URL_PREFIX_SLASHES[$uri[1]])) {
                $uri = \substr($uri, 1);
            }

            if (isset(Route::URL_PREFIX_SLASHES[$path[-1]])) {
                $uri = \substr($uri, 1);
            }

            \preg_match(Route::PRIORITY_REGEX, $this->data['path'] = ('/' . \ltrim($path, '/')) . $uri, $pM);
            $this->data['prefix'] = !empty($pM[1] ?? null) ? $pM[1] : null;
        }

        return $this;
    }

    /**
     * Sets the route path pattern.
     *
     * @return $this
     */
    public function path(string $pattern)
    {
        if (\preg_match(Route::RCA_PATTERN, $pattern, $matches, \PREG_UNMATCHED_AS_NULL)) {
            if (null !== $matches[1]) {
                $this->data['schemes'][$matches[1]] = true;
            }

            if (null !== $matches[2]) {
                $this->data['hosts'][$matches[2]] = true;
            }

            if (null !== $matches[5]) {
                $handler = $matches[4] ?? $this->data['handler'] ?? null;
                $this->data['handler'] = !empty($handler) ? [$handler, $matches[5]] : $matches[5];
            }

            if (empty($matches[3])) {
                throw new UriHandlerException(\sprintf('The route pattern "%s" is invalid as route path must be present in pattern.', $pattern));
            }

            \preg_match(Route::PRIORITY_REGEX, $this->data['path'] = '/' . \ltrim($matches[3], '/'), $pM);
            $this->data['prefix'] = !empty($pM[1] ?? null) ? $pM[1] : null;
        }

        return $this;
    }

    /**
     * Sets the requirement for the HTTP method.
     *
     * @param string $methods the HTTP method(s) name
     *
     * @return $this
     */
    public function method(string ...$methods)
    {
        foreach ($methods as $method) {
            $this->data['methods'][\strtoupper($method)] = true;
        }

        return $this;
    }

    /**
     * Sets the requirement of host on this Route.
     *
     * @param string $hosts The host for which this route should be enabled
     *
     * @return $this
     */
    public function domain(string ...$hosts)
    {
        foreach ($hosts as $host) {
            \preg_match(Route::URL_PATTERN, $host, $matches, \PREG_UNMATCHED_AS_NULL);

            if (isset($matches[1])) {
                $this->data['schemes'][$matches[1]] = true;
            }

            if (isset($matches[2])) {
                $this->data['hosts'][$matches[2]] = true;
            }
        }

        return $this;
    }

    /**
     * Sets the requirement of domain scheme on this Route.
     *
     * @param string ...$schemes
     *
     * @return $this
     */
    public function scheme(string ...$schemes)
    {
        foreach ($schemes as $scheme) {
            $this->data['schemes'][$scheme] = true;
        }

        return $this;
    }

    /**
     * Sets the route name.
     *
     * @return $this
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
     * @return $this
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
     * @return $this
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
     * @return $this
     */
    public function run($to)
    {
        if (isset($this->data['namespace'])) {
            $to = $this->resolveNamespace($this->data['namespace'], $to);
            unset($this->data['namespace']); // No longer needed.
        }

        $this->data['handler'] = $to;

        return $this;
    }

    /**
     * Sets the missing namespace on route's handler.
     *
     * @throws InvalidControllerException if $namespace is invalid
     *
     * @return $this
     */
    public function namespace(string $namespace)
    {
        if (!empty($namespace)) {
            if ('\\' === $namespace[-1]) {
                throw new InvalidControllerException(\sprintf('Namespace "%s" provided for routes must not end with a "\\".', $namespace));
            }

            if (isset($this->data['handler'])) {
                $this->data['handler'] = $this->resolveNamespace($namespace, $this->data['handler']);
            } else {
                $this->data['namespace'] = ($this->data['namespace'] ?? '') . $namespace;
            }
        }

        return $this;
    }

    /**
     * Attach a named middleware group(s) to route.
     *
     * @return $this
     */
    public function piped(string ...$to)
    {
        foreach ($to as $namedMiddleware) {
            $this->data['middlewares'][$namedMiddleware] = true;
        }

        return $this;
    }

    /**
     * Sets the requirement for a route variable.
     *
     * @param string|string[] $regexp The regexp to apply
     *
     * @return $this
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
     * @return $this
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
     * @return $this
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
     * @return $this
     */
    public function defaults(array $values)
    {
        foreach ($values as $variable => $default) {
            $this->default($variable, $default);
        }

        return $this;
    }

    public function hasMethod(string $method): bool
    {
        return isset($this->data['methods'][$method]);
    }

    public function hasScheme(string $scheme): bool
    {
        return empty($s = $this->data['schemes'] ?? []) || isset($s[$scheme]);
    }

    public function getName(): ?string
    {
        return $this->data['name'] ?? null;
    }

    public function getPath(): string
    {
        return $this->data['path'] ?? '/';
    }

    /**
     * @return array<int,string>
     */
    public function getMethods(): array
    {
        return \array_keys($this->data['methods'] ?? []);
    }

    /**
     * @return array<int,string>
     */
    public function getSchemes(): array
    {
        return \array_keys($this->data['schemes'] ?? []);
    }

    /**
     * @return array<int,string>
     */
    public function getHosts(): array
    {
        return \array_keys($this->data['hosts'] ?? []);
    }

    /**
     * @return array<int|string,mixed>
     */
    public function getArguments(): array
    {
        return $this->data['arguments'] ?? [];
    }

    /**
     * @return mixed
     */
    public function getHandler()
    {
        return $this->data['handler'] ?? null;
    }

    /**
     * @return array<int|string,mixed>
     */
    public function getDefaults(): array
    {
        return $this->data['defaults'] ?? [];
    }

    /**
     * @return array<string,string|string[]>
     */
    public function getPatterns(): array
    {
        return $this->data['patterns'] ?? [];
    }

    /**
     * Return the list of attached grouped middlewares.
     *
     * @return array<int,string>
     */
    public function getPiped(): array
    {
        return \array_keys($this->data['middlewares'] ?? []);
    }

    /**
     * Return's the static prefixed portion of the route path else null.
     *
     * @see Flight\Routing\RouteCollection::getRoutes()
     */
    public function getStaticPrefix(): ?string
    {
        return $this->data['prefix'] ?? null;
    }

    /**
     * @internal skip throwing an exception and return existing $controller
     *
     * @param callable|object|string|string[] $controller
     *
     * @return mixed
     */
    protected function resolveNamespace(string $namespace, $controller)
    {
        if ($controller instanceof ResourceHandler) {
            return $controller->namespace($namespace);
        }

        if (\is_string($controller) && '\\' === $controller[0]) {
            $controller = $namespace . $controller;
        } elseif ((\is_array($controller) && 2 == \count($controller, \COUNT_RECURSIVE)) && \is_string($controller[0])) {
            $controller[0] = $this->resolveNamespace($namespace, $controller[0]);
        }

        return $controller;
    }
}
