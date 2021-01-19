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

namespace Flight\Routing\Traits;

use Biurad\Annotations\LoaderInterface;
use Flight\Routing\DebugRoute;
use Flight\Routing\Exceptions\RouteNotFoundException;
use Flight\Routing\Interfaces\RouteInterface;
use Flight\Routing\Interfaces\RouteListenerInterface;
use Flight\Routing\Interfaces\RouteListInterface;
use Flight\Routing\Interfaces\RouteMatcherInterface;
use Flight\Routing\Router;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

trait RouterTrait
{
    use ResolveTrait;
    use DumperTrait;

    /** @var RouteMatcherInterface */
    private $matcher;

    /** @var ResponseFactoryInterface */
    private $responseFactory;

    /** @var UriFactoryInterface */
    private $uriFactory;

    /** @var null|DebugRoute */
    private $debug;

    /** @var RouteInterface[] */
    private $routes = [];

    /** @var RouteListenerInterface[] */
    private $listeners = [];

    /** @var array<int,array<string,mixed>> */
    private $attributes = [];

    /**
     * Gets the router routes
     *
     * @return RouteInterface[]
     */
    public function getRoutes(): array
    {
        return \array_values($this->cachedRoutes ?: $this->routes);
    }

    /**
     * Gets allowed methods
     *
     * @return string[]
     */
    public function getAllowedMethods(): array
    {
        $methods = [];

        foreach ($this->getRoutes() as $route) {
            foreach ($route->getMethods() as $method) {
                $methods[$method] = true;
            }
        }

        return \array_keys($methods);
    }

    /**
     * Gets a route for the given name
     *
     * @param string $name
     *
     * @throws RouteNotFoundException
     *
     * @return RouteInterface
     */
    public function getRoute(string $name): RouteInterface
    {
        $routes = $this->cachedRoutes ?: $this->routes;

        if (!isset($routes[$name])) {
            throw new RouteNotFoundException(\sprintf('No route found for the name "%s".', $name));
        }

        return $routes[$name];
    }

    /**
     * Get the profiled routes
     *
     * @return null|DebugRoute
     */
    public function getProfile(): ?DebugRoute
    {
        return $this->debug;
    }

    /**
     * Set profiling for routes
     */
    public function setProfile(): void
    {
        $this->debug = new DebugRoute();
    }

    /**
     * Set Namespace for route handlers/controllers
     *
     * @param string $namespace
     */
    public function setNamespace(string $namespace): void
    {
        $this->namespace = \rtrim($namespace, '\\/') . '\\';
    }

    /**
     * Adds parameters.
     *
     * This method implements a fluent interface.
     *
     * @param array<string,mixed> $parameters The parameters
     * @param int                 $type
     */
    public function addParameters(array $parameters, int $type = Router::TYPE_REQUIREMENT): void
    {
        foreach ($parameters as $key => $regex) {
            if (Router::TYPE_DEFAULT === $type) {
                $this->attributes[Router::TYPE_DEFAULT] = [$key => $regex];

                continue;
            }

            $this->attributes[Router::TYPE_REQUIREMENT] = [$key => $regex];
        }
    }

    /**
     * Adds the given route(s) listener to the router
     *
     * @param RouteListenerInterface ...$listeners
     */
    public function addRouteListener(RouteListenerInterface ...$listeners): void
    {
        foreach ($listeners as $listener) {
            $this->listeners[] = $listener;
        }
    }

    /**
     * Load routes from annotation.
     *
     * @param LoaderInterface $loader
     */
    public function loadAnnotation(LoaderInterface $loader): void
    {
        foreach ($loader->load() as $annotation) {
            if ($annotation instanceof RouteListInterface) {
                $this->addRoute(...$annotation->getRoutes());
            }
        }
    }

    /**
     * Get merged default parameters.
     *
     * @param RouteInterface $route
     *
     * @return array<string,string> Merged default parameters
     */
    private function mergeDefaults(RouteInterface $route): array
    {
        $defaults = $route->getDefaults();

        foreach ($route->getArguments() as $key => $value) {
            if (!isset($defaults[$key]) || null !== $value) {
                $defaults[$key] = $value;
            }
        }

        return $defaults;
    }

    /**
     * Merge Router attributes in route default and patterns.
     *
     * @param RouteInterface $route
     *
     * @return RouteInterface
     */
    private function mergeAttributes(RouteInterface $route): RouteInterface
    {
        foreach ($this->attributes as $type => $attributes) {
            if (Router::TYPE_DEFAULT === $type) {
                $route->setDefaults($attributes);

                continue;
            }

            $route->setPatterns($attributes);
        }

        return $route;
    }
}
