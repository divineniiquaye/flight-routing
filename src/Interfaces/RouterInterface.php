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

namespace Flight\Routing\Interfaces;

use Fig\Http\Message\RequestMethodInterface;
use Flight\Routing\Exceptions\DuplicateRouteException;
use Flight\Routing\Route;
use Flight\Routing\RouteCollection;

/**
 * RouterInterface is the interface that all Router classes must implement.
 *
 * This interface is the concatenation of UrlMatcherInterface and UrlGeneratorInterface.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
interface RouterInterface extends RouteMatcherInterface, RequestMethodInterface
{
    /**
     * Standard HTTP methods for browser requests.
     */
    public const HTTP_METHODS_STANDARD = [
        self::METHOD_HEAD,
        self::METHOD_GET,
        self::METHOD_POST,
        self::METHOD_PUT,
        self::METHOD_PATCH,
        self::METHOD_DELETE,
        self::METHOD_PURGE,
        self::METHOD_OPTIONS,
        self::METHOD_TRACE,
        self::METHOD_CONNECT,
    ];

    /**
     * Adds the given route(s) to the router
     *
     * @param Route ...$routes
     *
     * @throws DuplicateRouteException
     */
    public function addRoute(Route ...$routes): void;

    /**
     * Gets a collection of routes instances into router.
     *
     * WARNING: This method should never be used at runtime as it is SLOW.
     *          You might use it in a cache warmer though.
     *
     * @return RouteCollection A RouteCollection instance
     */
    public function getCollection(): RouteCollection;
}
