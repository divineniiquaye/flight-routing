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

namespace Flight\Routing\Concerns;

trait RouteGroup
{
    /**
     * Group routes with the given attributes.
     *
     * @param array           $attributes
     * @param \Closure|string $routes
     *
     * @return $this
     */
    public function group(array $attributes, $routes)
    {
        // Backup current properties
        $oldName = $this->currentName;
        $oldPrefix = $this->currentPrefix;
        $oldDomain = $this->currentDomain;
        $oldNamespace = $this->currentNamespace;
        $oldMiddleware = $this->currentMiddleware;

        // Set name for the group
        if (isset($attributes[GroupAttributes::NAME])) {
            $this->currentName = $attributes[GroupAttributes::NAME];
        }

        // Set domain for the group
        if (isset($attributes[GroupAttributes::DOMAIN])) {
            $this->currentDomain = $attributes[GroupAttributes::DOMAIN];
        }

        // Set namespace for the group
        if (isset($attributes[GroupAttributes::NAMESPACE])) {
            $this->currentNamespace = $attributes[GroupAttributes::NAMESPACE];
        }

        // Set prefix for the group
        if (isset($attributes[GroupAttributes::PREFIX])) {
            $this->currentPrefix = $this->currentPrefix.$attributes[GroupAttributes::PREFIX];
        }

        // Set middleware for the group
        if (isset($attributes[GroupAttributes::MIDDLEWARE])) {
            if (is_array($attributes[GroupAttributes::MIDDLEWARE]) == false) {
                $attributes[GroupAttributes::MIDDLEWARE] = [$attributes[GroupAttributes::MIDDLEWARE]];
            }

            $this->currentMiddleware = array_merge($attributes[GroupAttributes::MIDDLEWARE], $this->currentMiddleware);
        }

        // Run the group body closure
        $this->loadRoutes($routes);

        // Restore properties
        $this->currentName = $oldName;
        $this->currentDomain = $oldDomain;
        $this->currentPrefix = $oldPrefix;
        $this->currentNamespace = $oldNamespace;
        $this->currentMiddleware = $oldMiddleware;

        return $this;
    }

    /**
     * Load the provided routes.
     *
     * @param \Closure|string $routes
     *
     * @return mixed
     */
    private function loadRoutes(&$routes)
    {
        if ($routes instanceof \Closure) {
            return $routes($this);
        }

        return @require $routes;
    }
}
