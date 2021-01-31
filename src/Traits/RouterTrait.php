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
use Flight\Routing\Interfaces\RouteMatcherInterface;
use Flight\Routing\Route;
use Flight\Routing\RouteCollection;
use Flight\Routing\RouteResolver;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

trait RouterTrait
{
    use MiddlewareTrait;
    use DumperTrait;

    /** @var null|object|RouteMatcherInterface */
    private $matcher;

    /** @var ResponseFactoryInterface */
    private $responseFactory;

    /** @var UriFactoryInterface */
    private $uriFactory;

    /** @var null|DebugRoute */
    private $debug;

    /** @var RouteCollection */
    private $routes;

    /** @var null|Route */
    private $route;

    /** @var RouteResolver */
    private $resolver;

    /** @var array<string,mixed> */
    private $options = [];

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

        if (null !== $route = $this->routes->find($name)) {
            return $route;
        }

        throw new RouteNotFoundException(\sprintf('No route found for the name "%s".', $name));
    }

    /**
     * Get the profiled routes
     *
     * @return DebugRoute
     */
    public function getProfile(): DebugRoute
    {
        if (isset($this->options['debug'])) {
            foreach ($this->getCollection()->getRoutes() as $route) {
                $this->debug->addProfile(new DebugRoute($route->getName(), $route));
            }
        }

        return $this->debug;
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
        $param    = $route->getArguments();
        $excludes = ['_arguments' => true, '_compiler' => true, '_domain' => true];

        foreach ($defaults as $key => $value) {
            if (isset($excludes[$key])) {
                continue;
            }

            if (isset($param[$key]) || (!\is_int($key) && null !== $value)) {
                $route->argument($key, $value);
            }
        }
    }
}
