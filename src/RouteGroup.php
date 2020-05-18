<?php

declare(strict_types=1);

/*
 * This code is under BSD 3-Clause "New" or "Revised" License.
 *
 * PHP version 7 and above required
 *
 * @category  RoutingManager
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 Biurad Group (https://biurad.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * @link      https://www.biurad.com/projects/routingmanager
 * @since     Version 0.1
 */

namespace Flight\Routing;

use Closure;
use Flight\Routing\Interfaces\CallableResolverInterface;
use Flight\Routing\Interfaces\RouteGroupInterface;
use Flight\Routing\Interfaces\RouterProxyInterface;

class RouteGroup implements RouteGroupInterface
{
    /**
     * @var callable|string
     */
    protected $callable;

    /**
     * @var CallableResolverInterface
     */
    protected $callableResolver;

    /**
     * @var RouterProxyInterface
     */
    protected $routeProxy;

    /**
     * @var array
     */
    protected $attributes = [];

    /**
     * @param array                     $attributes
     * @param callable|string|object    $callable
     * @param CallableResolverInterface $callableResolver
     * @param RouterProxyInterface      $routeProxy
     */
    public function __construct(array $attributes, $callable, CallableResolverInterface $callableResolver, RouterProxyInterface $routeProxy)
    {
        $this->attributes = $attributes;
        $this->callable = $callable;
        $this->routeProxy = $routeProxy;
        $this->callableResolver = $callableResolver->addInstanceToClosure($this->routeProxy);
    }

    /**
     * {@inheritdoc}
     */
    public function collectRoutes(): RouteGroupInterface
    {
        $this->loadGroupRoutes($this->callable);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions(): array
    {
        return array_filter($this->attributes);
    }

    /**
     * Load the provided routes from group.
     *
     * @param Closure|callable|string $routes
     *
     * @return mixed
     */
    protected function loadGroupRoutes(&$routes)
    {
        $callable = $this->callableResolver->resolve($routes);

        return $callable($this->routeProxy);
    }
}
