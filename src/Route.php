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
 * Value object representing a single route.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class Route
{
    use Traits\DataTrait;

    /**
     * A Pattern to Locates appropriate route by name, support dynamic route allocation using following pattern:
     * Pattern route:   `pattern/*<controller@action>`
     * Default route: `*<controller@action>`
     * Only action:   `pattern/*<action>`.
     */
    public const RCA_PATTERN = '#^(?:([a-z]+)\:)?(?:\/{2}([^\/]+))?([^*]*)(?:\*\<(?:([\w+\\\\]+)\@)?(\w+)\>)?$#u';

    /**
     * A Pattern to match protocol, host and port from a url.
     *
     * Examples of urls that can be matched: http://en.example.domain, {sub}.example.domain, https://example.com:34, example.com, etc.
     */
    public const URL_PATTERN = '#^(?:([a-z]+)\:\/{2})?([^\/]+)?$#u';

    /**
     * A Pattern to match the route's priority.
     *
     * If route path matches, 1 is expected return else 0 should be return as priority index.
     */
    public const PRIORITY_REGEX = '#^([\/\w+][^<[{:]+\b)(.*)#';

    /**
     * Slashes supported on browser when used.
     */
    public const URL_PREFIX_SLASHES = ['/' => '/', ':' => ':', '-' => '-', '_' => '_', '~' => '~', '@' => '@'];

    /** @var array<int,string> Default methods for route. */
    public const DEFAULT_METHODS = [Router::METHOD_GET, Router::METHOD_HEAD];

    /**
     * Create a new Route constructor.
     *
     * @param string          $pattern The route pattern
     * @param string|string[] $methods the route HTTP methods
     * @param mixed           $handler The PHP class, object or callable that returns the response when matched
     */
    public function __construct(string $pattern, $methods = self::DEFAULT_METHODS, $handler = null)
    {
        $this->data = ['handler' => $handler];

        foreach ((array) $methods as $method) {
            $this->data['methods'][\strtoupper($method)] = true;
        }

        if (!empty($pattern)) {
            $this->path($pattern);
        }
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
        $route->data += $properties['data'] ?? \array_diff_key($properties, ['path' => null, 'methods' => [], 'handler' => null]);

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
    public static function to(string $pattern, $methods = self::DEFAULT_METHODS, $handler = null)
    {
        return new static($pattern, $methods, $handler);
    }

    /**
     * Sets a custom key and value into route
     *
     * @param mixed $value
     *
     * @return $this
     */
    public function setData(string $key, $value)
    {
        $this->data[$key] = $value;

        return $this;
    }

    /**
     * Get the route's data.
     *
     * @return array<string,mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function generateRouteName(string $prefix): string
    {
        $routeName = \implode('_', $this->getMethods()) . '_' . $prefix . $this->data['path'] ?? '';
        $routeName = \str_replace(['/', ':', '|', '-'], '_', $routeName);
        $routeName = (string) \preg_replace('/[^a-z0-9A-Z_.]+/', '', $routeName);

        return (string) \preg_replace(['/\_+/', '/\.+/'], ['_', '.'], $routeName);
    }
}
