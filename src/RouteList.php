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

use ArrayIterator;
use Countable;
use Flight\Routing\Interfaces\RouteGroupInterface;
use Flight\Routing\Interfaces\RouteInterface;
use Flight\Routing\Interfaces\RouteListInterface;
use IteratorAggregate;

/**
 * A RouteCollection represents a set of Route instances.
 *
 * When adding a route at the end of the collection, an existing route
 * with the same name is removed first. So there can only be one route
 * with a given name.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Tobias Schultze <http://tobion.de>
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
final class RouteList implements RouteListInterface, IteratorAggregate, Countable
{
    /** @var RouteInterface[] */
    private $list = [];

    public function __clone()
    {
        foreach ($this->list as $index => $route) {
            $this->list[$index] = clone $route;
        }
    }

    /**
     * Gets the current RouteCollection as an Iterator that includes all routes.
     *
     * It implements \IteratorAggregate.
     *
     * @see all()
     *
     * @return ArrayIterator<int,RouteInterface> An \ArrayIterator object for iterating over routes
     */
    public function getIterator()
    {
        return new ArrayIterator($this->getRoutes());
    }

    /**
     * Gets the number of Routes in this collection.
     *
     * @return int The number of routes
     */
    public function count()
    {
        return \iterator_count($this);
    }

    /**
     * {@inheritdoc}
     */
    public function getRoutes(): array
    {
        return $this->list;
    }

    /**
     * {@inheritdoc}
     */
    public function add(RouteInterface $route): RouteListInterface
    {
        $this->list[] = $route;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addCollection(RouteListInterface $collection): void
    {
        // we need to remove all routes with the same names first because just replacing them
        // would not place the new route at the end of the merged array
        foreach ($collection->getRoutes() as $index => $route) {
            unset($this->list[$index]);

            $this->list[$index] = $route;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function addForeach(RouteInterface ...$routes): RouteListInterface
    {
        foreach ($routes as $route) {
            $this->add($route);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addRoute(string $name, array $methods, string $pattern, $handler): RouteInterface
    {
        $this->add($route = new Route($name, $methods, $pattern, $handler));

        return $route;
    }

    /**
     * {@inheritdoc}
     */
    public function group($callable): RouteGroupInterface
    {
        $collector = new static();
        $callable($collector);

        $this->addForeach(...$routes = $collector->getRoutes());

        return new RouteGroup($routes);
    }

    /**
     * {@inheritdoc}
     */
    public function head(string $name, string $pattern, $callable): RouteInterface
    {
        return $this->addRoute($name, [Route::METHOD_HEAD], $pattern, $callable);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $name, string $pattern, $callable): RouteInterface
    {
        return $this->addRoute($name, [Route::METHOD_GET, Route::METHOD_HEAD], $pattern, $callable);
    }

    /**
     * {@inheritdoc}
     */
    public function post(string $name, string $pattern, $callable): RouteInterface
    {
        return $this->addRoute($name, [Route::METHOD_POST], $pattern, $callable);
    }

    /**
     * {@inheritdoc}
     */
    public function put(string $name, string $pattern, $callable): RouteInterface
    {
        return $this->addRoute($name, [Route::METHOD_PUT], $pattern, $callable);
    }

    /**
     * {@inheritdoc}
     */
    public function patch(string $name, string $pattern, $callable): RouteInterface
    {
        return $this->addRoute($name, [Route::METHOD_PATCH], $pattern, $callable);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $name, string $pattern, $callable): RouteInterface
    {
        return $this->addRoute($name, [Route::METHOD_DELETE], $pattern, $callable);
    }

    /**
     * {@inheritdoc}
     */
    public function options(string $name, string $pattern, $callable): RouteInterface
    {
        return $this->addRoute($name, [Route::METHOD_OPTIONS], $pattern, $callable);
    }

    /**
     * {@inheritdoc}
     */
    public function any(string $name, string $pattern, $callable): RouteInterface
    {
        return $this->addRoute($name, Route::HTTP_METHODS_STANDARD, $pattern, $callable);
    }

    /**
     * {@inheritdoc}
     */
    public function resource(string $name, string $pattern, $resource): RouteInterface
    {
        if (\is_callable($resource)) {
            throw new Exceptions\InvalidControllerException(
                'Resource handler type should be a string or object class, but not a callable'
            );
        }

        return $this->any($name . '__restful', $pattern, [$resource, $name]);
    }

    /**
     * {@inheritdoc}
     */
    public function withDefaults(array $defaults): void
    {

        foreach ($this->list as $route) {
            $route->setDefaults($defaults);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function withName(string $name): void
    {
        foreach ($this->list as $route) {
            $route->setName($name . $route->getName());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function withPrefix(string $prefix): void
    {
        foreach ($this->list as $route) {
            $route->addPrefix($prefix);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function withDomain(string $domain): void
    {
        foreach ($this->list as $route) {
            $route->setDomain($domain);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function withScheme(string ...$schemes): void
    {
        foreach ($this->list as $route) {
            $route->setScheme(...$schemes);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function withMethod(string ...$methods): void
    {
        foreach ($this->list as $route) {
            $route->addMethod(...$methods);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function withMiddleware(...$middlewares): void
    {
        foreach ($this->list as $route) {
            $route->addMiddleware(...$middlewares);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function withPatterns(array $patterns): void
    {
        foreach ($this->list as $route) {
            $route->setPatterns($patterns);
        }
    }
}
