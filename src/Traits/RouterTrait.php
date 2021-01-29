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
use Flight\Routing\Interfaces\RouteListenerInterface;
use Flight\Routing\Interfaces\RouteMatcherInterface;
use Flight\Routing\Route;
use Flight\Routing\RouteCollection;
use Flight\Routing\Router;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

trait RouterTrait
{
    use ResolveTrait;
    use DumperTrait;

    /** @var null|RouteMatcherInterface */
    private $matcher;

    /** @var ResponseFactoryInterface */
    private $responseFactory;

    /** @var UriFactoryInterface */
    private $uriFactory;

    /** @var null|DebugRoute */
    private $debug;

    /** @var RouteCollection */
    private $routes;

    /** @var RouteCollectionenerInterface[] */
    private $listeners = [];

    /** @var array<int,mixed> */
    private $attributes = [];

    /**
     * Gets allowed methods
     *
     * @return string[]
     */
    public function getAllowedMethods(): array
    {
        $methods = [];

        foreach ($this->getCollection()->getRoutes() as $route) {
            foreach ($route->getMethods() as $method => $has) {
                $methods[$method] = $has;
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
     * @return Route
     */
    public function getRoute(string $name): Route
    {
        // To Allow merging incase routes after this method doesn't exist
        $this->routes->getRoutes();

        if (null !== $this->routes->find($name)) {
            return $this->routes->find($name);
        }

        throw new RouteNotFoundException(\sprintf('No route found for the name "%s".', $name));
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
        if (Router::TYPE_DEFAULT === $type) {
            $this->attributes[Router::TYPE_DEFAULT] = $parameters;
        } elseif (Router::TYPE_CACHE === $type) {
            $this->attributes[Router::TYPE_CACHE] = \current($parameters);
        } elseif (Router::TYPE_REQUIREMENT === $type) {
            $this->attributes[Router::TYPE_REQUIREMENT] = $parameters;
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
            if ($annotation instanceof RouteCollection) {
                $this->addRoute(...$annotation->getRoutes());
            }
        }
    }

    /**
     * Get merged default parameters.
     *
     * @param Route $route
     */
    private function mergeDefaults(Route $route): void
    {
        $defaults = $route->getDefaults();
        $param    = $defaults['_arguments'] ?? [];
        $excludes = [
            '_arguments' => true,
            '_compiler' => true,
            '_domain' => true,
        ];

        foreach ($defaults as $key => $value) {
            if (isset($excludes[$key])) {
                continue;
            }

            if (
                (isset($param[$key]) && null === $param[$key]) ||
                (!\is_int($key) && null !== $value)
            ) {
                $route->argument($key, $value);
            }
        }
    }

    /**
     * Merge Router attributes in route default and patterns.
     *
     * @param Route $route
     *
     * @return Route
     */
    private function mergeAttributes(Route $route): Route
    {
        foreach ($this->attributes as $type => $attributes) {
            if (Router::TYPE_DEFAULT === $type) {
                foreach ($attributes as $variable => $default) {
                    $route->default($variable, $default);
                }
            } elseif (Router::TYPE_REQUIREMENT === $type) {
                foreach ($attributes as $variable => $regexp) {
                    $route->assert($variable, $regexp);
                }
            }
        }

        return $route;
    }
}
