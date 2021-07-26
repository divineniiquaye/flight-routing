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

use Flight\Routing\Exceptions\{InvalidControllerException, MethodNotAllowedException, UriHandlerException};
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface, UriInterface};
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Value object representing a single route.
 *
 * @method string getPath() Gets the route path.
 * @method string|null getName() Gets the route name.
 * @method string[] getMethods() Gets the route methods.
 * @method string[] getSchemes() Gets the route domain schemes.
 * @method string[] getDomain() Gets the route host.
 * @method mixed getController() Gets the route handler.
 * @method array getPatterns() Gets the route pattern placeholder assert.
 * @method array getDefaults() Gets the route default settings.
 * @method array getArguments() Gets the arguments passed to route handler as parameters.
 * @method array getAll() Gets all the routes properties.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class Route
{
    use Traits\CastingTrait;

    /**
     * A Pattern to Locates appropriate route by name, support dynamic route allocation using following pattern:
     * Pattern route:   `pattern/*<controller@action>`
     * Default route: `*<controller@action>`
     * Only action:   `pattern/*<action>`.
     *
     * @var string
     */
    public const RCA_PATTERN = '#^(?:([a-z]+)\:)?(?:\/{2}([^\/]+))?([^*]+?)(?:\*\<(?:([\w\\\\]+)\@)?(\w+)\>)?$#u';

    /**
     * A Pattern to match protocol, host and port from a url.
     *
     * Examples of urls that can be matched: http://en.example.domain, {sub}.example.domain, https://example.com:34, example.com, etc.
     *
     * @var string
     */
    public const URL_PATTERN = '#^(?:([a-z]+)\:\/{2})?([^\/]+)?$#u';

    /**
     * Slashes supported on browser when used.
     *
     * @var string[]
     */
    public const URL_PREFIX_SLASHES = ['/' => '/', ':' => ':', '-' => '-', '_' => '_', '~' => '~', '@' => '@'];

    /**
     * Default methods for route.
     */
    public const DEFAULT_METHODS = [Router::METHOD_GET, Router::METHOD_HEAD];

    protected const SUPPORTED_GETTER_METHODS = [
        'controller' => 'controller',
        'methods' => 'methods',
        'schemes' => 'schemes',
        'domain' => 'domain',
        'name' => 'name',
        'path' => 'path',
        'patterns' => 'patterns',
        'defaults' => 'defaults',
    ];

    /** @var RouteCollection|null */
    private $collection = null;

    /**
     * Create a new Route constructor.
     *
     * @param string          $pattern The route pattern
     * @param string|string[] $methods The route HTTP methods. Multiple methods can be supplied as an array
     *                                 or string combined with `|`.
     * @param mixed           $handler The PHP class, object or callable that returns the response when matched
     */
    public function __construct(string $pattern, $methods = self::DEFAULT_METHODS, $handler = null)
    {
        $this->controller = $handler;
        $this->path = $this->castRoute($pattern);

        if (!empty($methods)) {
            $this->methods = \array_map('strtoupper', (array) $methods);
        }
    }

    /**
     * @internal This is handled different by router
     *
     * @return self
     */
    public static function __set_state(array $properties)
    {
        $recovered = new self($properties['path'], $properties['methods'], $properties['controller']);
        unset($properties['path'], $properties['controller'], $properties['methods']);

        foreach ($properties as $name => $property) {
            $recovered->{$name} = $property;
        }

        return $recovered;
    }

    /**
     * @internal
     */
    public function __serialize(): array
    {
        return $this->get('all');
    }

    /**
     * @internal
     *
     * @param array<string,mixed>
     */
    public function __unserialize(array $data): void
    {
        foreach ($data as $parameter => $value) {
            $this->{$parameter} = $value;
        }
    }

    /**
     * @param string  $method
     * @param mixed[] $arguments
     *
     * @throws \BadMethodCallException
     *
     * @return mixed
     */
    public function __call($method, $arguments)
    {
        $routeMethod = (string) \preg_replace('/^get([A-Z]{1}[a-z]+)$/', '\1', $method, 1);
        $routeMethod = \strtolower($routeMethod);

        if (!empty($arguments)) {
            throw new \BadMethodCallException(\sprintf('Arguments passed into "%s" method not supported, as method does not exist.', $routeMethod));
        }

        return $this->get($routeMethod);
    }

    /**
     * Invoke the response from route handler.
     *
     * @param null|callable(mixed,array) $handlerResolver
     *
     * @return ResponseInterface|string
     */
    public function __invoke(ServerRequestInterface $request, ?callable $handlerResolver = null)
    {
        $handler = $this->controller;

        if ($handler instanceof ResponseInterface) {
            return $handler;
        }

        if ($handler instanceof RequestHandlerInterface) {
            return $handler->handle($request);
        }

        if ($handler instanceof Handlers\ResourceHandler) {
            $handler = $handler(\strtolower($request->getMethod()));
        }

        $handler = $this->castHandler($request, $handlerResolver, $handler);

        if (!$handler) {
            throw new InvalidControllerException(
                \sprintf('Response type of "%s" for route "%s" is not allowed in PSR7 response body stream.', \get_debug_type($handler), $this->name)
            );
        }

        return $handler;
    }

    /**
     * Sets the route path prefix.
     */
    public function prefix(string $path): self
    {
        $this->path = $this->castPrefix($this->path, $path);

        return $this;
    }

    /**
     * Sets the route path pattern.
     */
    public function path(string $pattern): self
    {
        $this->path = $this->castRoute($pattern);

        return $this;
    }

    /**
     * Sets the route name.
     */
    public function bind(string $routeName): self
    {
        $this->name = $routeName;

        return $this;
    }

    /**
     * Sets the route code that should be executed when matched.
     *
     * @param mixed $to PHP class, object or callable that returns the response when matched
     */
    public function run($to): self
    {
        $this->controller = $to;

        return $this;
    }

    /**
     * Sets the missing namespace on route's handler.
     *
     * @throws InvalidControllerException if $namespace is invalid
     */
    public function namespace(string $namespace): self
    {
        if ('' !== $namespace) {
            if ('\\' === $namespace[-1]) {
                throw new InvalidControllerException(\sprintf('Namespace "%s" provided for routes must not end with a "\\".', $namespace));
            }

            $this->controller = $this->castNamespace($namespace, $this->controller);
        }

        return $this;
    }

    /**
     * Sets the requirement for a route variable.
     *
     * @param string          $variable The variable name
     * @param string|string[] $regexp   The regexp to apply
     */
    public function assert(string $variable, $regexp): self
    {
        $this->patterns[$variable] = $regexp;

        return $this;
    }

    /**
     * Sets the requirements for a route variable.
     *
     * @param array<string,string|string[]> $regexps The regexps to apply
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
     * @param string $variable The variable name
     * @param mixed  $default  The default value
     */
    public function default(string $variable, $default): self
    {
        $this->defaults[$variable] = $default;

        return $this;
    }

    /**
     * Sets the default values for a route variables.
     *
     * @param array<string,mixed> $values
     */
    public function defaults(array $values): self
    {
        foreach ($values as $variable => $default) {
            $this->default($variable, $default);
        }

        return $this;
    }

    /**
     * Sets the parameter value for a route handler.
     *
     * @param string $variable The parameter name
     * @param mixed  $value    The parameter value
     */
    public function argument(string $variable, $value): self
    {
        if (\is_numeric($value)) {
            $value = (int) $value;
        } elseif (\is_string($value)) {
            $value = \rawurldecode($value);
        }

        $this->defaults['_arguments'][$variable] = $value;

        return $this;
    }

    /**
     * Sets the parameter values for a route handler.
     *
     * @param array<int|string> $variables The route handler parameters
     */
    public function arguments(array $variables): self
    {
        foreach ($variables as $variable => $value) {
            $this->argument($variable, $value);
        }

        return $this;
    }

    /**
     * Sets the requirement for the HTTP method.
     *
     * @param string $methods the HTTP method(s) name
     */
    public function method(string ...$methods): self
    {
        foreach ($methods as $method) {
            $this->methods[] = \strtoupper($method);
        }

        return $this;
    }

    /**
     * Sets the requirement of host on this Route.
     *
     * @param string $hosts The host for which this route should be enabled
     */
    public function domain(string ...$hosts): self
    {
        foreach ($hosts as $host) {
            \preg_match(Route::URL_PATTERN, $host, $matches, \PREG_UNMATCHED_AS_NULL);

            if (isset($matches[1])) {
                $this->schemes[$matches[1]] = true;
            }

            if (isset($matches[2])) {
                $this->domain[] = $matches[2];
            }
        }

        return $this;
    }

    /**
     * Sets the requirement of domain scheme on this Route.
     *
     * @param string ...$schemes
     */
    public function scheme(string ...$schemes): self
    {
        foreach ($schemes as $scheme) {
            $this->schemes[] = \strtolower($scheme);
        }

        return $this;
    }

    /**
     * Asserts methods and host's scheme.
     *
     * @throws MethodNotAllowedException|UriHandlerException
     */
    public function match(string $method, UriInterface $uri): self
    {
        if (!\in_array($method, $this->methods)) {
            throw new MethodNotAllowedException($this->methods, $uri->getPath(), $method);
        }

        if (!empty($schemes = $this->schemes)) {
            if (\in_array($uri->getScheme(), $schemes, true)) {
                return $this;
            }

            throw new UriHandlerException(\sprintf('Unfortunately current scheme "%s" is not allowed on requested uri [%s].', $uri->getScheme(), $uri->getPath()), 400);
        }

        return $this;
    }

    /**
     * Get any of (name, path, domain, defaults, schemes, domain, controller, patterns, middlewares).
     * And also accepts "all" and "arguments".
     *
     * @throws \BadMethodCallException if $name does not exist as property
     *
     * @return mixed
     */
    public function get(string $name)
    {
        if (isset(static::SUPPORTED_GETTER_METHODS[$name])) {
            return $this->{$name};
        }

        if ('all' === $name) {
            return \array_map([$this, 'get'], static::SUPPORTED_GETTER_METHODS);
        }

        if ('arguments' === $name) {
            return $this->defaults['_arguments'] ?? [];
        }

        throw new \BadMethodCallException(\sprintf('Invalid call for "%s" as method, %s(\'%1$s\') not supported.', $name, __METHOD__));
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

    public function generateRouteName(string $prefix): string
    {
        $routeName = \implode('_', $this->methods) . '_' . $prefix . $this->path;
        $routeName = \str_replace(['/', ':', '|', '-'], '_', $routeName);
        $routeName = (string) \preg_replace('/[^a-z0-9A-Z_.]+/', '', $routeName);

        return (string) \preg_replace(['/\_+/', '/\.+/'], ['_', '.'], $routeName);
    }
}
