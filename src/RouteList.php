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

use Flight\Routing\Interfaces\RouteGroupInterface;
use Flight\Routing\Interfaces\RouteInterface;
use Flight\Routing\Interfaces\RouteListInterface;

/**
 * The route broker.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
final class RouteList implements RouteListInterface
{
    /** @var RouteInterface[] */
    private $list = [];

    public function __construct()
    {
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
}
