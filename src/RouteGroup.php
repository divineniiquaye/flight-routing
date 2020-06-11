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

    /**
     * Merge route groups into a new array.
     *
     * @param RouteGroup $group
     */
    public function mergeBackupAttributes(?RouteGroupInterface $group): self
    {
        $new = $this->attributes;
        $old = null !== $group ? $group->getOptions() : [];

        if (isset($new[self::DOMAIN])) {
            unset($old[self::DOMAIN]);
        }

        $newAttributes = array_merge(
            $this->formatName($new, $old),
            [
                self::NAMESPACE     => $this->formatNamespace($new, $old),
                self::PREFIX        => $this->formatPrefix($new, $old),
                self::DEFAULTS      => $this->formatAttributes(self::DEFAULTS, $new, $old),
                self::MIDDLEWARES   => $this->formatAttributes(self::MIDDLEWARES, $new, $old),
                self::REQUIREMENTS  => $this->formatAttributes(self::REQUIREMENTS, $new, $old),
                self::SCHEMES       => $this->formatSchemes($new, $old),
            ]
        );

        $this->attributes = array_filter($newAttributes);

        return $this;
    }

    /**
     * Format the namespace for the new group attributes.
     *
     * @param array $new
     * @param array $old
     *
     * @return string|null
     */
    protected function formatNamespace($new, $old): ?string
    {
        if (isset($new[self::NAMESPACE])) {
            if (isset($old[self::NAMESPACE]) && strpos($new[self::NAMESPACE], '\\') !== 0) {
                return rtrim($old[self::NAMESPACE], '\\').'\\'.rtrim($new[self::NAMESPACE], '\\');
            }

            return rtrim($new[self::NAMESPACE], '\\');
        }

        return $old[self::NAMESPACE] ?? null;
    }

    /**
     * Format the prefix for the new group attributes.
     *
     * @param array $new
     * @param array $old
     *
     * @return string|null
     */
    protected function formatPrefix($new, $old): ?string
    {
        $old = $old[self::PREFIX] ?? null;

        return isset($new[self::PREFIX]) ? $old.$new[self::PREFIX] : $old;
    }

    /**
     * Format the "wheres" for the new group attributes.
     *
     * @param array $new
     * @param array $old
     *
     * @return array|null
     */
    protected function formatSchemes(array $new, array $old): ?array
    {
        if (!isset($old[self::SCHEMES], $new[self::SCHEMES])) {
            return null;
        }

        return array_merge($old[self::SCHEMES] ?? [], $new[self::SCHEMES] ?? []);
    }

    /**
     * Format for the new group attributes.
     *
     * @param string $old
     * @param array  $new
     * @param array  $old
     *
     * @return array
     */
    protected function formatAttributes(string $key, array $new, array $old): array
    {
        return array_merge($old[$key] ?? [], $new[$key] ?? []);
    }

    /**
     * Format the "name" clause of the new group attributes.
     *
     * @param array $new
     * @param array $old
     *
     * @return array
     */
    protected function formatName(array $new, array $old): array
    {
        if (isset($old[self::NAME])) {
            $new[self::NAME] = $old[self::NAME].($new[self::NAME] ?? '');
        }

        return $new;
    }
}
