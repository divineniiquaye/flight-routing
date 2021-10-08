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

use Flight\Routing\{RouteCollection, Router};
use Flight\Routing\Exceptions\MethodNotAllowedException;
use Psr\Http\Message\UriInterface;

/**
 * An abstract route extended to routes.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
abstract class AbstractRoute
{
    /** @var array<int,string> Default methods for route. */
    public const DEFAULT_METHODS = [Router::METHOD_GET, Router::METHOD_HEAD];

    /** @var array<string,mixed> */
    protected array $data;

    /** @var array<int,string> */
    protected array $middlewares = [];

    private ?RouteCollection $collection = null;

    /**
     * Create a new Route constructor.
     *
     * @param string          $pattern The route pattern
     * @param string|string[] $methods the route HTTP methods
     * @param mixed           $handler The PHP class, object or callable that returns the response when matched
     */
    public function __construct(string $pattern, $methods = self::DEFAULT_METHODS, $handler = null)
    {
        $this->data = ['path' => $pattern, 'handler' => $handler];

        foreach ((array) $methods as $method) {
            $this->data['methods'][\strtoupper($method)] = true;
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
    public static function to(string $pattern, $methods = self::DEFAULT_METHODS, $handler = null)
    {
        return new static($pattern, $methods, $handler);
    }

    /**
     * Asserts route.
     *
     * @throws MethodNotAllowedException
     *
     * @return static
     */
    public function match(string $method, UriInterface $uri)
    {
        if (!\array_key_exists($method, $this->data['methods'] ?? [])) {
            throw new MethodNotAllowedException($this->getMethods(), $uri->getPath(), $method);
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

    public function getName(): ?string
    {
        return $this->data['name'] ?? null;
    }

    public function getPath(): string
    {
        $path = $this->data['path'] ?? '/';

        if ('/' === $path[0]) {
            return $path;
        }

        return '/' . $path;
    }

    /**
     * @return array<int,string>
     */
    public function getMethods(): array
    {
        return \array_keys($this->data['methods'] ?? []);
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
        return $this->middlewares;
    }

    public function generateRouteName(string $prefix): string
    {
        $routeName = \implode('_', $this->getMethods()) . '_' . $prefix . $this->data['path'] ?? '';
        $routeName = \str_replace(['/', ':', '|', '-'], '_', $routeName);
        $routeName = (string) \preg_replace('/[^a-z0-9A-Z_.]+/', '', $routeName);

        return (string) \preg_replace(['/\_+/', '/\.+/'], ['_', '.'], $routeName);
    }
}
