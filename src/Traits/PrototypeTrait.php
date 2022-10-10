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
use Flight\Routing\Handlers\ResourceHandler;

/**
 * A trait providing route method prototyping.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
trait PrototypeTrait
{
    protected int $defaultIndex = -1;
    protected bool $asRoute = false, $sorted = false;

    /** @var array<string,mixed> */
    protected array $prototypes = [];

    /** @var array<int,array<string,mixed>> */
    protected array $routes = [];

    /** @var array<int,self> */
    protected array $groups = [];

    /**
     * Set route's data by calling supported route method in collection.
     *
     * @param array<string,mixed>|true $routeData An array is a list of route method bindings
     *                                            Else if true, route bindings can be prototyped
     *                                            to all registered routes
     *
     * @return $this
     *
     * @throws \InvalidArgumentException if route not defined before calling this method
     */
    public function prototype(array|bool $routeData): self
    {
        if (true === $routeData) {
            $this->asRoute = false;

            return $this;
        }

        foreach ($routeData as $routeMethod => $arguments) {
            \call_user_func_array([$this, $routeMethod], \is_array($arguments) ? $arguments : [$arguments]);
        }

        return $this;
    }

    /**
     * Ending of group chaining stack. (use with caution!).
     *
     * RISK: This method can break the collection, call this method
     * only on the last route of a group stack which the $return parameter
     * of the group method is set true.
     *
     * @return $this
     */
    public function end(): self
    {
        return $this->parent ?? $this;
    }

    /**
     * Set the route's path.
     *
     * @return $this
     *
     * @throws \InvalidArgumentException if you is not set
     */
    public function path(string $pattern): self
    {
        if (!$this->asRoute) {
            throw new \InvalidArgumentException('Cannot use the "path()" method if route not defined.');
        }

        if (1 === \preg_match(static::RCA_PATTERN, $pattern, $matches, \PREG_UNMATCHED_AS_NULL)) {
            isset($matches[1]) && $this->routes[$this->defaultIndex]['schemes'][$matches[1]] = true;

            if (isset($matches[2])) {
                if ('/' !== ($matches[3][0] ?? '')) {
                    throw new UriHandlerException(\sprintf('The route pattern "%s" is invalid as route path must be present in pattern.', $pattern));
                }
                $this->routes[$this->defaultIndex]['hosts'][$matches[2]] = true;
            }

            if (isset($matches[5])) {
                $handler = $matches[4] ?? $this->routes[$this->defaultIndex]['handler'] ?? null;
                $this->routes[$this->defaultIndex]['handler'] = !empty($handler) ? [$handler, $matches[5]] : $matches[5];
            }

            \preg_match(static::PRIORITY_REGEX, $pattern = $matches[3], $m, \PREG_UNMATCHED_AS_NULL);
            $this->routes[$this->defaultIndex]['prefix'] = $m[1] ?? null;
        }

        $this->routes[$this->defaultIndex]['path'] = '/'.\ltrim($pattern, '/');

        return $this;
    }

    /**
     * Set the route's unique name identifier,.
     *
     * @return $this
     *
     * @throws \InvalidArgumentException if you is not set
     */
    public function bind(string $routeName): self
    {
        if (!$this->asRoute) {
            throw new \InvalidArgumentException('Cannot use the "bind()" method if route not defined.');
        }
        $this->routes[$this->defaultIndex]['name'] = $routeName;

        return $this;
    }

    /**
     * Set the route's handler.
     *
     * @param mixed $to PHP class, object or callable that returns the response when matched
     *
     * @return $this
     *
     * @throws \InvalidArgumentException if you is not set
     */
    public function run(mixed $to): self
    {
        if (!$this->asRoute) {
            throw new \InvalidArgumentException('Cannot use the "run()" method if route not defined.');
        }

        if (!empty($namespace = $this->routes[$this->defaultIndex]['namespace'] ?? null)) {
            unset($this->routes[$this->defaultIndex]['namespace']);
        }
        $this->routes[$this->defaultIndex]['handler'] = $this->resolveHandler($to, $namespace);

        return $this;
    }

    /**
     * Set the route(s) default value for it's placeholder or required argument.
     *
     * @return $this
     */
    public function default(string $variable, mixed $default): self
    {
        if ($this->asRoute) {
            $this->routes[$this->defaultIndex]['defaults'][$variable] = $default;
        } elseif (-1 === $this->defaultIndex && empty($this->groups)) {
            $this->prototypes['defaults'] = \array_merge_recursive($this->prototypes['defaults'] ?? [], [$variable => $default]);
        } else {
            foreach ($this->routes as &$route) {
                $route['defaults'] = \array_merge_recursive($route['defaults'] ?? [], [$variable => $default]);
            }
            $this->resolveGroup(__FUNCTION__, [$variable, $default]);
        }

        return $this;
    }

    /**
     * Set the routes(s) default value for it's placeholder or required argument.
     *
     * @param array<string,mixed> $values
     *
     * @return $this
     */
    public function defaults(array $values): self
    {
        foreach ($values as $variable => $default) {
            $this->default($variable, $default);
        }

        return $this;
    }

    /**
     * Set the route(s) placeholder requirement.
     *
     * @param array<int,string>|string $regexp The regexp to apply
     *
     * @return $this
     */
    public function placeholder(string $variable, string|array $regexp): self
    {
        if ($this->asRoute) {
            $this->routes[$this->defaultIndex]['placeholders'][$variable] = $regexp;
        } elseif (-1 === $this->defaultIndex && empty($this->groups)) {
            $this->prototypes['placeholders'] = \array_merge_recursive($this->prototypes['placeholders'] ?? [], [$variable => $regexp]);
        } else {
            foreach ($this->routes as &$route) {
                $route['placeholders'] = \array_merge_recursive($route['placeholders'] ?? [], [$variable => $regexp]);
            }

            $this->resolveGroup(__FUNCTION__, [$variable, $regexp]);
        }

        return $this;
    }

    /**
     * Set the route(s) placeholder requirements.
     *
     * @param array<string,array<int,string>|string> $placeholders The regexps to apply
     *
     * @return $this
     */
    public function placeholders(array $placeholders): self
    {
        foreach ($placeholders as $placeholder => $value) {
            $this->placeholder($placeholder, $value);
        }

        return $this;
    }

    /**
     * Set the named parameter supplied to route(s) handler's constructor/factory.
     *
     * @return $this
     */
    public function argument(string $parameter, mixed $value): self
    {
        $resolver = fn ($value) => \is_numeric($value) ? (int) $value : (\is_string($value) ? \rawurldecode($value) : $value);

        if ($this->asRoute) {
            $this->routes[$this->defaultIndex]['arguments'][$parameter] = $resolver($value);
        } elseif (-1 === $this->defaultIndex && empty($this->groups)) {
            $this->prototypes['arguments'] = \array_merge_recursive($this->prototypes['arguments'] ?? [], [$parameter => $value]);
        } else {
            foreach ($this->routes as &$route) {
                $route['arguments'] = \array_merge_recursive($route['arguments'] ?? [], [$parameter => $resolver($value)]);
            }
            $this->resolveGroup(__FUNCTION__, [$parameter, $value]);
        }

        return $this;
    }

    /**
     * Set the named parameters supplied to route(s) handler's constructor/factory.
     *
     * @param array<string,mixed> $parameters The route handler parameters
     *
     * @return $this
     */
    public function arguments(array $parameters): self
    {
        foreach ($parameters as $parameter => $value) {
            $this->argument($parameter, $value);
        }

        return $this;
    }

    /**
     * Set the missing namespace for route(s) handler(s).
     *
     * @return $this
     *
     * @throws InvalidControllerException if namespace does not ends with a \
     */
    public function namespace(string $namespace): self
    {
        if ('\\' !== $namespace[-1]) {
            throw new InvalidControllerException(\sprintf('Cannot set a route\'s handler namespace "%s" without an ending "\\".', $namespace));
        }

        if ($this->asRoute) {
            $handler = &$this->routes[$this->defaultIndex]['handler'] ?? null;

            if (!empty($handler)) {
                $handler = $this->resolveHandler($handler, $namespace);
            } else {
                $this->routes[$this->defaultIndex][__FUNCTION__] = $namespace;
            }
        } elseif (-1 === $this->defaultIndex && empty($this->groups)) {
            $this->prototypes[__FUNCTION__][] = $namespace;
        } else {
            foreach ($this->routes as &$route) {
                $route['handler'] = $this->resolveHandler($route['handler'] ?? null, $namespace);
            }
            $this->resolveGroup(__FUNCTION__, [$namespace]);
        }

        return $this;
    }

    /**
     * Set the route(s) HTTP request method(s).
     *
     * @return $this
     */
    public function method(string ...$methods): self
    {
        if ($this->asRoute) {
            foreach ($methods as $method) {
                $this->routes[$this->defaultIndex]['methods'][\strtoupper($method)] = true;
            }

            return $this;
        }

        $routeMethods = \array_fill_keys(\array_map('strtoupper', $methods), true);

        if (-1 === $this->defaultIndex && empty($this->groups)) {
            $this->prototypes['methods'] = \array_merge($this->prototypes['methods'] ?? [], $routeMethods);
        } else {
            foreach ($this->routes as &$route) {
                $route['methods'] += $routeMethods;
            }
            $this->resolveGroup(__FUNCTION__, $methods);
        }

        return $this;
    }

    /**
     * Set route(s) HTTP host scheme(s).
     *
     * @return $this
     */
    public function scheme(string ...$schemes): self
    {
        if ($this->asRoute) {
            foreach ($schemes as $scheme) {
                $this->routes[$this->defaultIndex]['schemes'][$scheme] = true;
            }

            return $this;
        }
        $routeSchemes = \array_fill_keys($schemes, true);

        if (-1 === $this->defaultIndex && empty($this->groups)) {
            $this->prototypes['schemes'] = \array_merge($this->prototypes['schemes'] ?? [], $routeSchemes);
        } else {
            foreach ($this->routes as &$route) {
                $route['schemes'] = \array_merge($route['schemes'] ?? [], $routeSchemes);
            }
            $this->resolveGroup(__FUNCTION__, $schemes);
        }

        return $this;
    }

    /**
     * Set the route(s) HTTP host name(s).
     *
     * @return $this
     */
    public function domain(string ...$domains): self
    {
        $resolver = static function (array &$route, array $domains): void {
            foreach ($domains as $domain) {
                if (1 === \preg_match('/^(?:([a-z]+)\:\/{2})?([^\/]+)?$/u', $domain, $m, \PREG_UNMATCHED_AS_NULL)) {
                    if (isset($m[1])) {
                        $route['schemes'][$m[1]] = true;
                    }

                    if (isset($m[2])) {
                        $route['hosts'][$m[2]] = true;
                    }
                }
            }
        };

        if ($this->asRoute) {
            $resolver($this->routes[$this->defaultIndex], $domains);
        } elseif (-1 === $this->defaultIndex && empty($this->groups)) {
            $this->prototypes[__FUNCTION__] = \array_merge($this->prototypes[__FUNCTION__] ?? [], $domains);
        } else {
            foreach ($this->routes as &$route) {
                $resolver($route, $domains);
            }
            $this->resolveGroup(__FUNCTION__, $domains);
        }

        return $this;
    }

    /**
     * Set prefix path which should be prepended to route(s) path.
     *
     * @return $this
     */
    public function prefix(string $path): self
    {
        $resolver = static function (string $prefix, string $path): string {
            if ('/' !== ($prefix[0] ?? '')) {
                $prefix = '/'.$prefix;
            }

            if ($prefix[-1] === $path[0] || 1 === \preg_match('/^\W+$/', $prefix[-1])) {
                return $prefix.\substr($path, 1);
            }

            return $prefix.$path;
        };

        if ($this->asRoute) {
            \preg_match(
                static::PRIORITY_REGEX,
                $this->routes[$this->defaultIndex]['path'] = $resolver(
                    $path,
                    $this->routes[$this->defaultIndex]['path'] ?? '',
                ),
                $m,
                \PREG_UNMATCHED_AS_NULL
            );
            $this->routes[$this->defaultIndex]['prefix'] = $m[1] ?? null;
        } elseif (-1 === $this->defaultIndex && empty($this->groups)) {
            $this->prototypes[__FUNCTION__][] = $path;
        } else {
            foreach ($this->routes as &$route) {
                \preg_match(static::PRIORITY_REGEX, $route['path'] = $resolver($path, $route['path']), $m);
                $route['prefix'] = $m[1] ?? null;
            }

            $this->resolveGroup(__FUNCTION__, [$path]);
        }

        return $this;
    }

    /**
     * Set a set of named grouped middleware(s) to route(s).
     *
     * @return $this
     */
    public function piped(string ...$to): self
    {
        if ($this->asRoute) {
            foreach ($to as $middleware) {
                $this->routes[$this->defaultIndex]['middlewares'][$middleware] = true;
            }

            return $this;
        }
        $routeMiddlewares = \array_fill_keys($to, true);

        if (-1 === $this->defaultIndex && empty($this->groups)) {
            $this->prototypes['middlewares'] = \array_merge($this->prototypes['middlewares'] ?? [], $routeMiddlewares);
        } else {
            foreach ($this->routes as &$route) {
                $route['middlewares'] = \array_merge($route['middlewares'] ?? [], $routeMiddlewares);
            }
            $this->resolveGroup(__FUNCTION__, $to);
        }

        return $this;
    }

    /**
     * Set a custom key and value to route(s).
     *
     * @return $this
     */
    public function set(string $key, mixed $value): self
    {
        if (\in_array($key, [
            'name',
            'handler',
            'arguments',
            'namespace',
            'middlewares',
            'methods',
            'placeholders',
            'prefix',
            'hosts',
            'schemes',
            'defaults',
        ], true)) {
            throw new \InvalidArgumentException(\sprintf('Cannot replace the default "%s" route binding.', $key));
        }

        if ($this->asRoute) {
            $this->routes[$this->defaultIndex][$key] = $value;
        } elseif (-1 === $this->defaultIndex && empty($this->groups)) {
            $this->prototypes[$key] = !\is_array($value) ? $value : \array_merge($this->prototypes[$key] ?? [], $value);
        } else {
            foreach ($this->routes as &$route) {
                $route[$key] = \is_array($value) ? \array_merge($route[$key] ?? [], $value) : $value;
            }
            $this->resolveGroup(__FUNCTION__, [$key, $value]);
        }

        return $this;
    }

    protected function resolveHandler(mixed $handler, string $namespace = null): mixed
    {
        if (empty($namespace)) {
            return $handler;
        }

        if (\is_string($handler)) {
            if ('\\' === $handler[0] || \str_starts_with($handler, $namespace)) {
                return $handler;
            }
            $handler = $namespace.$handler;
        } elseif (\is_array($handler)) {
            if (2 !== \count($handler, \COUNT_RECURSIVE)) {
                throw new InvalidControllerException('Cannot use a non callable like array as route handler.');
            }

            if (\is_string($handler[0]) && !\str_starts_with($handler[0], $namespace)) {
                $handler[0] = $this->resolveHandler($handler[0], $namespace);
            }
        } elseif ($handler instanceof ResourceHandler) {
            $handler = $handler->namespace($namespace);
        }

        return $handler;
    }

    /**
     * @param array<int,mixed> $arguments
     */
    protected function resolveGroup(string $method, array $arguments): void
    {
        foreach ($this->groups as $group) {
            $asRoute = $group->asRoute;
            $group->asRoute = false;
            \call_user_func_array([$group, $method], $arguments);
            $group->asRoute = $asRoute;
        }
    }
}
