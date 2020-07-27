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

use Flight\Routing\Concerns\HttpMethods;
use Flight\Routing\Exceptions\DuplicateRouteException;
use Flight\Routing\Interfaces\RouteCollectionInterface;
use Flight\Routing\Interfaces\RouteFactory;
use Flight\Routing\Interfaces\RouteFactoryInterface;
use Flight\Routing\Interfaces\RouteGroupInterface;
use Flight\Routing\Interfaces\RouteInterface;

/**
 * Aggregate routes for the router.
 *
 * This class provides all(*) methods for creating path+HTTP method-based routes and
 * injecting them into the router:
 *
 * - head
 * - get
 * - post
 * - put
 * - patch
 * - delete
 * - any
 * - map
 *
 * A general `map()` method allows specifying multiple request methods and/or
 * arbitrary request methods when creating a path-based route.
 *
 * Internally, the class performs some checks for duplicate routes when
 * attaching via one of the exposed methods, and will raise an exception when a
 * collision occurs.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class RouteCollector implements Interfaces\RouteCollectorInterface
{
    /** @var RouteCollection */
    private $collection;

    /** @var RouteFactoryInterface */
    private $routeFactory;

    public function __construct(
        ?RouteCollectionInterface $collection = null,
        ?RouteFactoryInterface $routeFactory = null
    ) {
        $this->routeFactory = $routeFactory ?? new RouteFactory();
        $this->collection   = $collection ?? new RouteCollection();
    }

    /**
     * @return array
     */
    public function __debugInfo()
    {
        return [
            'routes' => \iterator_to_array($this->collection),
            'counts' => \iterator_count($this->collection),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getCollection(): RouteCollectionInterface
    {
        return $this->collection;
    }

    /**
     * {@inheritdoc}
     */
    public function group($callable): RouteGroupInterface
    {
        $collector = new self($this->collection, $this->routeFactory);

        $callable($collector);

        $this->collection->add(...$collector->collection);

        return new RouteGroup($collector->collection);
    }

    /**
     * {@inheritdoc}
     */
    public function head(string $name, string $pattern, $callable): RouteInterface
    {
        return $this->map($name, [HttpMethods::METHOD_HEAD], $pattern, $callable);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $name, string $pattern, $callable): RouteInterface
    {
        return $this->map($name, [HttpMethods::METHOD_GET, HttpMethods::METHOD_HEAD], $pattern, $callable);
    }

    /**
     * {@inheritdoc}
     */
    public function post(string $name, string $pattern, $callable): RouteInterface
    {
        return $this->map($name, [HttpMethods::METHOD_POST], $pattern, $callable);
    }

    /**
     * {@inheritdoc}
     */
    public function put(string $name, string $pattern, $callable): RouteInterface
    {
        return $this->map($name, [HttpMethods::METHOD_PUT], $pattern, $callable);
    }

    /**
     * {@inheritdoc}
     */
    public function patch(string $name, string $pattern, $callable): RouteInterface
    {
        return $this->map($name, [HttpMethods::METHOD_PATCH], $pattern, $callable);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $name, string $pattern, $callable): RouteInterface
    {
        return $this->map($name, [HttpMethods::METHOD_DELETE], $pattern, $callable);
    }

    /**
     * {@inheritdoc}
     */
    public function options(string $name, string $pattern, $callable): RouteInterface
    {
        return $this->map($name, [HttpMethods::METHOD_OPTIONS], $pattern, $callable);
    }

    /**
     * {@inheritdoc}
     */
    public function any(string $name, string $pattern, $callable): RouteInterface
    {
        return $this->map($name, HttpMethods::HTTP_METHODS_STANDARD, $pattern, $callable);
    }

    /**
     * {@inheritdoc}
     */
    public function map(string $name, array $methods, string $pattern, $handler): RouteInterface
    {
        return $this->routeFactory->createRoute($name, \array_map('strtoupper', $methods), $pattern, $handler);
    }

    /**
     * Determine if the route is duplicated in the current list.
     *
     * Checks if a route with the same path exists already in the list;
     * if so, and it responds to any of the $methods indicated, raises
     * a DuplicateRouteException indicating a duplicate route.
     *
     * @param string   $path
     * @param string[] $methods
     *
     * @throws DuplicateRouteException on duplicate route detection
     */
    private function checkForDuplicateRoute(string $path, array $methods): void
    {
        $allowed = [];
        $matches = \array_filter(
            $this->collection,
            function (RouteInterface $route) use ($path, $methods, &$allowed): bool {
                if ($path === $route->getPath()) {
                    foreach ($methods as $method) {
                        if (\in_array($method, $route->getMethods(), true)) {
                            $allowed[] = $method;

                            return true;
                        }
                    }

                    return false;
                }

                return false;
            }
        );

        if (!empty($matches)) {
            throw new DuplicateRouteException(
                \sprintf(
                    'Duplicate route detected; path "%s" answering to methods [%s]',
                    \reset($matches)->getPath(),
                    \implode(',', $allowed ?? ['(any)'])
                )
            );
        }
    }
}
