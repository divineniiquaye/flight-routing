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
use Flight\Routing\Route;
use Psr\Http\Message\UriInterface;

trait ValidationTrait
{
    /**
     * Check if given request domain matches given route domain.
     *
     * @param string[]                 $routeDomains
     * @param string                   $requestDomain
     * @param array<int|string,string> $parameters
     *
     * @return bool
     */
    protected function compareDomain(array $routeDomains, string $requestDomain, array &$parameters): bool
    {
        foreach ($routeDomains as $routeDomain) {
            if (1 === \preg_match($routeDomain, $requestDomain, $parameters)) {
                return true;
            }
        }

        return empty($routeDomains);
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
        return 1 === \preg_match($routeUri, $requestUri, $parameters);
    }

    /**
     * Asserts the Route's method and domain scheme.
     *
     * @param Route        $route
     * @param UriInterface $requestUri
     * @param string       $method
     */
    protected function assertRoute(Route $route, UriInterface $requestUri, string $method): void
    {
        if (!$this->compareMethod($route->getMethods(), $method)) {
            throw new MethodNotAllowedException(\array_keys($route->getMethods()), $requestUri->getPath(), $method);
        }

        if (!$this->compareScheme($route->getSchemes(), $requestUri->getScheme())) {
            throw new UriHandlerException(
                \sprintf(
                    'Unfortunately current scheme "%s" is not allowed on requested uri [%s]',
                    $requestUri->getScheme(),
                    $requestUri->getPath()
                ),
                400
            );
        }
    }

    /**
     * Asserts the Route'd host and port
     *
     * @param string $hostAndPort
     * @param string $requestPath
     *
     * @return UriHandlerException
     */
    protected function assertHost(string $hostAndPort, string $requestPath): UriHandlerException
    {
        return new UriHandlerException(
            \sprintf(
                'Unfortunately current domain "%s" is not allowed on requested uri [%s]',
                $hostAndPort,
                $requestPath
            ),
            400
        );
    }

    /**
     * Check if given request method matches given route method.
     *
     * @param string[] $routeMethods
     * @param string   $requestMethod
     *
     * @return bool
     */
    private function compareMethod(array $routeMethods, string $requestMethod): bool
    {
        return isset($routeMethods[$requestMethod]);
    }

    /**
     * Check if given request uri scheme matches given route scheme.
     *
     * @param string[] $routeSchemes
     * @param string   $requestScheme
     *
     * @return bool
     */
    private function compareScheme(array $routeSchemes, string $requestScheme): bool
    {
        return empty($routeSchemes) || isset($routeSchemes[$requestScheme]);
    }
}
