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

use Flight\Routing\Interfaces\CallableResolverInterface;
use Flight\Routing\Interfaces\RouteCollectorInterface;
use Flight\Routing\Interfaces\RouteGroupInterface;
use Flight\Routing\Interfaces\RouterProxyInterface;

use function array_filter;

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
     * @var string
     */
    protected $pattern;

    /**
     * @var array
     */
    protected $attributes = [];

    /**
     * @param string|null                  $pattern
     * @param array                        $attributes
     * @param callable|string              $callable
     * @param CallableResolverInterface    $callableResolver
     * @param RouteCollectorInterface      $routeProxy
     */
    public function __construct(?string $pattern, array $attributes, $callable, CallableResolverInterface $callableResolver, RouterProxyInterface $routeProxy)
    {
        $this->pattern = $pattern;
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
     * Set the Route Group Options
     *
     * @param array $attributes self::CONSTANT => $values
     */
    public function addOptions(array $attributes): void
    {
        foreach ($attributes as $name => $values) {
            $this->attributes[$name] = $values;
        }
    }

    /**
     * Get Route The Group Option.
     *
     * @param string $name
     * @return mixed
     */
    public function getOptions(): array
    {
        return array_filter($this->attributes);
    }

    /**
     * {@inheritdoc}
     */
    public function getPattern(): ?string
    {
        return $this->pattern;
    }

    /**
     * Load the provided routes from group.
     *
     * @param \Closure|callable|string $routes
     *
     * @return mixed
     */
    protected function loadGroupRoutes(&$routes)
    {
        $callable = $this->callableResolver->resolve($routes);
        return $callable($this->routeProxy);
    }
}
