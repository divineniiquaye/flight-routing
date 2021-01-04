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

use Flight\Routing\Exceptions\MethodNotAllowedException;
use Flight\Routing\Exceptions\UriHandlerException;
use Flight\Routing\Interfaces\RouteInterface;

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
     * Asserts the Route's method and domain scheme.
     *
     * @param RouteInterface   $route
     * @param array<int,mixed> $attributes
     */
    private function assertRoute(RouteInterface $route, array $attributes): void
    {
        [$method, $scheme, $path] = $attributes;

        if (!$this->compareMethod($route->getMethods(), $method)) {
            throw new MethodNotAllowedException($route->getMethods(), $path, $method);
        }

        if (!$this->compareScheme($route->getSchemes(), $scheme)) {
            throw new UriHandlerException(
                \sprintf('Unfortunately current scheme "%s" is not allowed on requested uri [%s]', $scheme, $path),
                400
            );
        }
    }
}
