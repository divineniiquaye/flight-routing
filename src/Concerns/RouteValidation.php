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

namespace Flight\Routing\Concerns;

trait RouteValidation
{
    /**
     * Check if given request method matches given route method.
     *
     * @param array|string $routeMethod
     * @param string       $requestMethod
     *
     * @return bool
     */
    protected function compareMethod($routeMethod, string $requestMethod): bool
    {
        if (\is_array($routeMethod) && !empty($routeMethod)) {
            return \in_array($requestMethod, $routeMethod, true);
        }

        return $routeMethod === $requestMethod;
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
    protected function compareDomain(?string $routeDomain, string $requestDomain, array &$parameters): bool
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
    protected function compareUri(string $routeUri, string $requestUri, array &$parameters): bool
    {
        return (bool) \preg_match($routeUri, $requestUri, $parameters);
    }

    /**
     * Check if given request uri scheme matches given route scheme.
     *
     * @param null|string|string[] $routeScheme
     * @param string               $requestScheme
     *
     * @return bool
     */
    protected function compareScheme($routeScheme, string $requestScheme): bool
    {
        if (\is_array($routeScheme) && !empty($routeScheme)) {
            return \in_array($requestScheme, $routeScheme, true);
        }

        return ($routeScheme === null || empty($routeScheme)) || $routeScheme === $requestScheme;
    }

    /**
     * Check if the user is on the right uri which was matched.
     * If matched returns null, else returns the path the user should be in.
     *
     * @param string $routeUri
     * @param string $requestUri
     *
     * @return null|string
     */
    protected function compareRedirection(string $routeUri, string $requestUri): ?string
    {
        // Resolve Request Uri.
        $newRequestUri = '/' === $requestUri ? '/' : \rtrim($requestUri, '/');
        $newRouteUri   = '/' === $routeUri ? $routeUri : \rtrim($routeUri, '/');

        $paths = [
            'path'      => \substr($requestUri, \strlen($newRequestUri)),
            'route'     => \substr($routeUri, \strlen($newRouteUri)),
        ];

        if (!empty($paths['route']) && $paths['route'] !== $paths['path']) {
            return $newRequestUri . $paths['route'];
        }

        if (empty($paths['route']) && $paths['route'] !== $paths['path']) {
            return $newRequestUri;
        }

        return null;
    }

    /**
     * Get merged default parameters.
     *
     * @param array<int|string,mixed> $params
     * @param array<string,string>    $defaults
     *
     * @return array<string,string> Merged default parameters
     */
    protected function mergeDefaults(array $params, array $defaults): array
    {
        foreach ($params as $key => $value) {
            if (!\is_int($key) && (!isset($defaults[$key]) || null !== $value)) {
                $defaults[$key] = $value;
            }
        }

        return $defaults;
    }
}
