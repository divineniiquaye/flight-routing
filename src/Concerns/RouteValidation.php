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

use function rtrim;
use function in_array;
use function is_array;
use function preg_match;
use function strlen;
use function substr;

trait RouteValidation
{
    /**
     * Check if given request method matches given route method.
     *
     * @param string|array|null $routeMethod
     * @param string            $requestMethod
     *
     * @return bool
     */
    protected function compareMethod($routeMethod, string $requestMethod): bool
    {
        if (is_array($routeMethod)) {
            return in_array($requestMethod, $routeMethod, true);
        }

        return $routeMethod === $requestMethod;
    }

    /**
     * Check if given request domain matches given route domain.
     *
     * @param string|null $routeDomain
     * @param string      $requestDomain
     * @param array       $parameters
     *
     * @return bool
     */
    protected function compareDomain(?string $routeDomain, string $requestDomain, array &$parameters): bool
    {
        return ($routeDomain === null || empty($routeDomain)) || preg_match($routeDomain, $requestDomain, $parameters);
    }

    /**
     * Check if given request uri matches given uri method.
     *
     * @param string $routeUri
     * @param string $requestUri
     * @param array  $parameters
     *
     * @return bool|int
     */
    protected function compareUri(string $routeUri, string $requestUri, array &$parameters)
    {
        return preg_match($routeUri, $requestUri, $parameters);
    }

    /**
     * Check if the user is on the right uri which was matched.
     * If matched returns null, else returns the path the user should be in.
     *
     * @param string $routeUri
     * @param string $requestUri
     *
     * @return string|null
     */
    protected function compareRedirection(string $routeUri, string $requestUri): ?string
    {

        // Resolve Request Uri.
        $newRequestUri  = '/' === $requestUri ? '/' : rtrim($requestUri, '/');
        $newRouteUri    = '/' === $routeUri ? $routeUri : rtrim($routeUri, '/');

        $paths = [
            'path'      => substr($requestUri, strlen($newRequestUri)),
            'route'     => substr($routeUri, strlen($newRouteUri))
        ];

        if (!empty($paths['route']) && $paths['route'] !== $paths['path']) {
            return $newRequestUri . $paths['route'];
        }

        if (empty($paths['route']) && $paths['route'] !== $paths['path']) {
            return $newRequestUri;
        }

        return null;
    }
}
