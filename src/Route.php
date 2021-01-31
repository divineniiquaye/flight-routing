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
 * Value object representing a single route.
 *
 * Internally, only those three properties are required. However, underlying
 * router implementations may allow or require additional information, such as
 * information defining how to generate a URL from the given route, qualifiers
 * for how segments of a route match, or even default values to use.
 *
 * __call() forwards method-calls to Route, but returns mixed contents.
 * listing Route's methods below, so that IDEs know they are valid
 *
 * @method string getPath() Gets the route path.
 * @method null|string getName() Gets the route name.
 * @method string[] getMethods() Gets the route methods.
 * @method string[] getSchemes() Gets the route domain schemes.
 * @method string[] getDomain() Gets the route host.
 * @method mixed getController() Gets the route handler.
 * @method array getMiddlewares() Gets the route middlewares.
 * @method array getPatterns() Gets the route pattern placeholder assert.
 * @method array getDefaults() Gets the route default settings.
 * @method array getArguments() Gets the arguments passed to route handler as parameters.
 * @method array getAll() Gets all the routes properties.
 *
 * @method Route asserts(array $patterns) Add an array of route named patterns.
 * @method Route defaults(array $values) Add an array of default values.
 * @method Route arguments(array $properties) Add an array of handler's arguments.
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
    public const RCA_PATTERN = '/^(?P<route>.*?)?(?P<handler>\*\<(?:(?<c>[a-zA-Z0-9\\\\]+?)\@)?(?<a>[a-zA-Z0-9_\-]+)?\>)?$/u';

    /**
     * A Pattern to match protocol, host and port from a url
     *
     * Examples of urls that can be matched:
     * http://en.example.domain
     * //example.domain
     * //example.com
     * https://example.com:34
     * //example.com
     * example.com
     * localhost:8000
     * {foo}.domain.com
     *
     * @var string
     */
    public const URL_PATTERN = '/^(?:(?P<scheme>https?)\:)?(?P<domain>(?:\/\/)?(?P<host>[^\/\*]+)?(\:\d+)?)\/*?$/u';

    /**
     * Create a new Route constructor.
     *
     * @param string $pattern The route pattern
     * @param string $methods The route HTTP methods. Multiple methods can be supplied,
     *                        delimited by a pipe character '|', eg. 'GET|POST'
     * @param mixed  $handler The PHP class, object or callable that returns the response when matched
     */
    public function __construct(string $pattern, string $methods = 'GET|HEAD', $handler = null)
    {
        $this->controller = $handler;
        $this->path       = $this->castRoute($pattern);

        if (!empty($methods)) {
            $this->method(...\explode('|', $methods));
        }
    }

    /**
     * @internal This is handled different by router
     *
     * @param array $properties
     */
    public static function __set_state(array $properties)
    {
        $recovered = new self($properties['path'], '', $properties['controller']);

        unset($properties['path'], $properties['controller']);

        foreach ($properties as $name => $property) {
            $recovered->{$name} = $property;
        }

        return $recovered;
    }

    /**
     * @param string   $method
     * @param string[] $arguments
     *
     * @return mixed
     */
    public function __call($method, $arguments)
    {
        if (\in_array($method, ['arguments', 'defaults', 'asserts'], true)) {
            foreach (\current($arguments) as $variable => $value) {
                $this->{\rtrim($method, 's')}($variable, $value);

                return $this;
            }
        }
        $routeMethod = \strtolower((string) \preg_replace('~^get([A-Z]{1}[a-z]+)$~', '\1', $method, 1));

        if (\in_array($routeMethod, ['all', 'arguments'], true)) {
            return $this->get($routeMethod);
        }

        if (!\property_exists(__CLASS__, $routeMethod)) {
            throw new \BadMethodCallException(
                \sprintf(
                    'Property "%s->%s" does not exist. should be one of [%s],' .
                    ' or arguments, prefixed with a \'get\' name; eg: getName().',
                    Route::class,
                    $method,
                    \join(', ', \array_keys($this->get('all')))
                )
            );
        }

        return $this->get($routeMethod);
    }

    /**
     * Sets the route path prefix.
     *
     * @param string $path
     *
     * @return Route $this The current Route instance
     */
    public function prefix(string $path): self
    {
        $this->path = $this->castPrefix($this->path, $path);

        return $this;
    }

    /**
     * Sets the route path pattern.
     *
     * @param string $pattern
     *
     * @return Route $this The current Route instance
     */
    public function path(string $pattern): self
    {
        $this->path = $this->castRoute($pattern);

        return $this;
    }

    /**
     * Sets the route name.
     *
     * @param string $routeName
     *
     * @return Route $this The current Route instance
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
     *
     * @return Route $this The current Route instance
     */
    public function run($to): self
    {
        $this->controller = $to;

        return $this;
    }

    /**
     * Sets the requirement for a route variable.
     *
     * @param string          $variable The variable name
     * @param string|string[] $regexp   The regexp to apply
     *
     * @return Route $this The current route instance
     */
    public function assert(string $variable, $regexp): self
    {
        $this->patterns[$variable] = $regexp;

        return $this;
    }

    /**
     * Sets the default value for a route variable.
     *
     * @param string $variable The variable name
     * @param mixed  $default  The default value
     *
     * @return Route $this The current Route instance
     */
    public function default(string $variable, $default): self
    {
        $this->defaults[$variable] = $default;

        return $this;
    }

    /**
     * Sets the parameter value for a route handler.
     *
     * @param int|string $variable The parameter name
     * @param mixed      $value    The parameter value
     *
     * @return Route $this The current Route instance
     */
    public function argument($variable, $value): self
    {
        if (!\is_int($variable)) {
            if (\is_numeric($value)) {
                $value = (int) $value;
            } elseif (\is_string($value)) {
                $value = \rawurldecode($value);
            }

            $this->defaults['_arguments'][$variable] = $value;
        }

        return $this;
    }

    /**
     * Sets the requirement for the HTTP method.
     *
     * @param string $methods the HTTP method(s) name
     *
     * @return Route $this The current Route instance
     */
    public function method(string ...$methods): self
    {
        foreach ($methods as $method) {
            $this->methods[\strtoupper($method)] = true;
        }

        return $this;
    }

    /**
     * Sets the requirement of host on this Route.
     *
     * @param string $hosts The host for which this route should be enabled
     *
     * @return Route $this The current Route instance
     */
    public function domain(string ...$hosts): self
    {
        foreach ($hosts as $host) {
            \preg_match(Route::URL_PATTERN, $host, $matches);

            if (isset($matches['scheme']) && !empty($scheme = $matches['scheme'])) {
                $this->schemes[$scheme] = true;
            }

            $this->domain[$matches['host'] ?? $host] = true;
        }

        return $this;
    }

    /**
     * Sets the requirement of domain scheme on this Route.
     *
     * @param string ...$schemes
     *
     * @return Route $this The current Route instance
     */
    public function scheme(string ...$schemes): self
    {
        foreach ($schemes as $scheme) {
            $this->schemes[$scheme] = true;
        }

        return $this;
    }

    /**
     * Sets the middleware(s) to handle before triggering the route handler
     *
     * @param mixed ...$middlewares
     *
     * @return Route $this The current Route instance
     */
    public function middleware(...$middlewares): self
    {
        /** @var int|string $index */
        foreach ($middlewares as $index => $middleware) {
            if (!\is_callable($middleware) && (\is_int($index) && \is_array($middleware))) {
                $this->middleware(...$middleware);

                continue;
            }

            $this->middlewares[] = $middleware;
        }

        return $this;
    }

    /**
     * Get any of (name, path, domain, defaults, schemes, domain, controller, patterns, middlewares).
     * And also accepts "all" and "arguments".
     *
     * @param string $name
     *
     * @return mixed
     */
    public function get(string $name)
    {
        if (\property_exists(__CLASS__, $name)) {
            return $this->{$name};
        }

        if ('all' === $name) {
            return [
                'controller'  => $this->controller,
                'methods'     => $this->methods,
                'schemes'     => $this->schemes,
                'domain'      => $this->domain,
                'name'        => $this->name,
                'path'        => $this->path,
                'patterns'    => $this->patterns,
                'middlewares' => $this->middlewares,
                'defaults'    => $this->defaults,
            ];
        }

        if ('arguments' === $name) {
            return $this->defaults['_arguments'] ?? [];
        }

        return null;
    }

    public function generateRouteName(string $prefix): string
    {
        $methods = \implode('_', \array_keys($this->methods)) . '_';

        $routeName = $methods . $prefix . $this->path;
        $routeName = \str_replace(['/', ':', '|', '-'], '_', $routeName);
        $routeName = (string) \preg_replace('/[^a-z0-9A-Z_.]+/', '', $routeName);

        // Collapse consecutive underscores down into a single underscore.
        $routeName = (string) \preg_replace('/_+/', '_', $routeName);

        return $routeName;
    }
}
