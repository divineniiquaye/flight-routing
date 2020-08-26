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

use Closure;
use Flight\Routing\Interfaces\RouteInterface;
use Flight\Routing\Router;

trait ValidationTrait
{
    /**
     * Check if given request method matches given route method.
     *
     * @param string[] $routeMethod
     * @param string   $requestMethod
     *
     * @return bool
     */
    private function compareMethod(array $routeMethod, string $requestMethod): bool
    {
        return \in_array($requestMethod, $routeMethod, true);
    }

    /**
     * Check if given request domain matches given route domain.
     *
     * @param null|string              $routeDomain
     * @param string                   $requestDomain
     * @param array<int|string,string> $parameters
     *
     * @return bool
     */
    private function compareDomain(?string $routeDomain, string $requestDomain, array &$parameters): bool
    {
        return ($routeDomain === null || empty($routeDomain)) ||
            (bool) \preg_match($routeDomain, $requestDomain, $parameters);
    }

    /**
     * Check if given request uri matches given uri method.
     *
     * @param string                   $routeUri
     * @param string                   $requestUri
     * @param array<int|string,string> $parameters
     *
     * @return bool
     */
    private function compareUri(string $routeUri, string $requestUri, array &$parameters): bool
    {
        return (bool) \preg_match($routeUri, $requestUri, $parameters);
    }

    /**
     * Check if given request uri scheme matches given route scheme.
     *
     * @param string[] $routeScheme
     * @param string   $requestScheme
     *
     * @return bool
     */
    private function compareScheme(array $routeScheme, string $requestScheme): bool
    {
        return empty($routeScheme) || \in_array($requestScheme, $routeScheme, true);
    }

    /**
     * Get merged default parameters.
     *
     * @param array<int|string,mixed> $params
     * @param array<string,string>    $defaults
     *
     * @return array<string,string> Merged default parameters
     */
    private function mergeDefaults(array $params, array $defaults): array
    {
        foreach ($params as $key => $value) {
            if (!\is_int($key) && (!isset($defaults[$key]) || null !== $value)) {
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

    /**
     * @param callable|object|string|string[] $controller
     *
     * @return callable|object|string|string[]
     */
    private function resolveController($controller)
    {
        if (null !== $this->namespace && (\is_string($controller) || !$controller instanceof Closure)) {
            if (
                \is_string($controller) &&
                !\class_exists($controller) &&
                false === \stripos($controller, $this->namespace)
            ) {
                $controller = \is_callable($controller) ? $controller : $this->namespace . $controller;
            }

            if (\is_array($controller) && (!\is_object($controller[0]) && !\class_exists($controller[0]))) {
                $controller[0] = $this->namespace . $controller[0];
            }
        }

        return $controller;
    }
}
