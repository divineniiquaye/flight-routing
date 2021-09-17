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

namespace Flight\Routing\Routes;

use Flight\Routing\Exceptions\{MethodNotAllowedException, InvalidControllerException};
use Flight\Routing\{Router, RouteCollection};
use Flight\Routing\Handlers\ResourceHandler;
use Psr\Http\Message\UriInterface;

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
 * @method string      getPath()      Gets the route path.
 * @method string|null getName()      Gets the route name.
 * @method string[]    getMethods()   Gets the route methods.
 * @method mixed       getHandler()   Gets the route handler.
 * @method array       getArguments() Gets the arguments passed to route handler as parameters.
 * @method array       getDefaults()  Gets the route default settings.
 * @method array       getPatterns()  Gets the route pattern placeholder assert.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class FastRoute
{
    /** Default methods for route. */
    public const DEFAULT_METHODS = [Router::METHOD_GET, Router::METHOD_HEAD];

    /** @var array<string,string> Getter methods supported by route */
    protected static $getter = [
        'name' => 'name',
        'path' => 'path',
        'methods' => 'methods*',
        'handler' => 'handler',
        'arguments' => 'arguments*',
        'defaults' => 'defaults*',
        'patterns' => 'patterns*',
    ];

    /** @var array<string,mixed> */
    protected $data = [];

    /** @var array<int,string> */
    protected $middlewares = [];

    /** @var RouteCollection|null */
    private $collection;

    /**
     * Create a new Route constructor.
     *
     * @param string          $pattern The route pattern
     * @param string|string[] $methods the route HTTP methods
     * @param mixed           $handler The PHP class, object or callable that returns the response when matched
     */
    public function __construct(string $pattern, $methods = self::DEFAULT_METHODS, $handler = null)
    {
        $this->data = [
            'path' => $pattern,
            'handler' => $handler,
            'methods' => !empty($methods) ? \array_map('strtoupper', (array) $methods) : [],
        ];
    }

    /**
     * @param string[] $arguments
     *
     * @throws \BadMethodCallException
     *
     * @return mixed
     */
    public function __call(string $method, array $arguments)
    {
        if (\str_starts_with($method = \strtolower($method), 'get')) {
            $method = \substr($method, 3);
        }

        if (!empty($arguments)) {
            throw new \BadMethodCallException(\sprintf('Arguments passed into "%s::%s(...)" not supported, method invalid.', __CLASS__, $method));
        }

        return $this->get($method);
    }

    /**
     * @internal
     */
    public function __serialize(): array
    {
        return $this->data;
    }

    /**
     * @internal
     *
     * @param array<string,mixed> $data
     */
    public function __unserialize(array $data): void
    {
        $this->data = $data;
    }

    /**
     * @internal
     *
     * @param array<string,mixed> $properties The route data properties
     *
     * @return static
     */
    public static function __set_state(array $properties)
    {
        $route = new static($properties['path'] ?? '', $properties['methods'] ?? [], $properties['handler'] ?? null);
        $route->data += \array_diff_key($properties, ['path' => null, 'methods' => [], 'handler' => null]);

        return $route;
    }

    /**
     * Create a new Route statically.
     *
     * @param string          $pattern The route pattern
     * @param string|string[] $methods the route HTTP methods
     * @param mixed           $handler The PHP class, object or callable that returns the response when matched
     *
     * @return static
     */
    public static function to(string $pattern, $methods = self::DEFAULT_METHODS, $handler = null): self
    {
        return new static($pattern, $methods, $handler);
    }

    /**
     * Asserts route.
     *
     * @throws MethodNotAllowedException
     *
     * @return $this
     */
    public function match(string $method, UriInterface $uri): static
    {
        if (!\in_array($method, $methods = $this->get('methods'), true)) {
            throw new MethodNotAllowedException($methods, $uri->getPath(), $method);
        }

        return $this;
    }

    /**
     * Sets the route path prefix.
     *
     * @return $this
     */
    public function prefix(string $path): self
    {
        $this->data['path'] = $path . $this->data['path'] ?? '';

        return $this;
    }

    /**
     * Sets the route path pattern.
     *
     * @return $this
     */
    public function path(string $pattern): self
    {
        $this->data['path'] = $pattern;

        return $this;
    }

    /**
     * Sets the requirement for the HTTP method.
     *
     * @param string $methods the HTTP method(s) name
     *
     * @return $this
     */
    public function method(string ...$methods): self
    {
        foreach ($methods as $method) {
            $this->data['methods'][] = \strtoupper($method);
        }

        return $this;
    }

    /**
     * Sets the route name.
     *
     * @return $this
     */
    public function bind(string $routeName): self
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
    public function argument(string $parameter, $value): self
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
    public function arguments(array $parameters): self
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
    public function run($to): self
    {
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
    public function namespace(string $namespace): self
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
     * @return $this
     */
    public function piped(string ...$to): self
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
     * @return $this
     */
    public function assert(string $variable, $regexp): self
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
    public function asserts(array $regexps): self
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
    public function default(string $variable, $default): self
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
    public function defaults(array $values): self
    {
        foreach ($values as $variable => $default) {
            $this->default($variable, $default);
        }

        return $this;
    }

    /**
     * Sets the route belonging to a particular collection.
     *
     * This method is kinda internal, only used in RouteCollection class,
     * and retrieved using this class end method.
     *
     * @internal used by RouteCollection class
     */
    public function belong(RouteCollection $to): void
    {
        $this->collection = $to;
    }

    /**
     * End a group stack or return self.
     */
    public function end(): ?RouteCollection
    {
        if (null !== $stack = $this->collection) {
            $this->collection = null; // Just remove it.
        }

        return $stack;
    }

    /**
     * Get a return from any valid key name of this class $getter static property.
     *
     * @throws \InvalidArgumentException if $name does not exist as property
     *
     * @return mixed
     */
    public function get(string $name)
    {
        if (null === $key = static::$getter[$name] ?? null) {
            throw new \InvalidArgumentException(\sprintf('Invalid call for "%s" in %s(\'%1$s\'), try any of [%s].', $name, __METHOD__, \implode(',', \array_keys(static::$getter))));
        }

        if ('*' === $key[-1]) {
            return \array_unique($this->data[\substr($key, 0, -1)] ?? []);
        }

        return $this->data[$key] ?? null;
    }

    /**
     * Return the list of attached grouped middlewares.
     *
     * @return array<int,string>
     */
    public function getPiped(): array
    {
        return $this->middlewares;
    }

    /**
     * Get the route's data.
     *
     * @return array<string,mixed>
     */
    public function getData(): array
    {
        return \array_map(function (string $property) {
            if ('*' === $property[-1]) {
                $property = \substr($property, 0, -1);
            }

            return $this->get($property);
        }, static::$getter);
    }

    public function generateRouteName(string $prefix): string
    {
        $routeName = \implode('_', $this->data['methods'] ?? []) . '_' . $prefix . $this->data['path'] ?? '';
        $routeName = \str_replace(['/', ':', '|', '-'], '_', $routeName);
        $routeName = (string) \preg_replace('/[^a-z0-9A-Z_.]+/', '', $routeName);

        return (string) \preg_replace(['/\_+/', '/\.+/'], ['_', '.'], $routeName);
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
