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

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

interface RouteGroupInterface
{
    /**
     * Adds the given default keys and values to all routes in the collection
     *
     * @param array<string,mixed> $defaults
     *
     * @return RouteGroupInterface
     */
    public function setDefaults(array $defaults): self;

    /**
     * Adds the given path prefix to all routes in the collection
     *
     * @param string $prefix
     *
     * @return RouteGroupInterface
     */
    public function addPrefix(string $prefix): self;

    /**
     * Adds the given path domain to all routes in the collection
     *
     * @param string $domain
     *
     * @return RouteGroupInterface
     */
    public function addDomain(string $domain): self;

    /**
     * Adds the given domain scheme(s) to all routes in the collection
     *
     * @param string ...$schemes
     *
     * @return RouteGroupInterface
     */
    public function addScheme(string ...$schemes): self;

    /**
     * Adds the given method(s) to all routes in the collection
     *
     * @param string ...$methods
     *
     * @return RouteGroupInterface
     */
    public function addMethod(string ...$methods): self;

    /**
     * Adds the given middleware(s) to all routes in the collection
     *
     * @param callable|MiddlewareInterface|RequestHandlerInterface|string ...$middlewares
     *
     * @return RouteGroupInterface
     */
    public function addMiddleware(...$middlewares): self;
}
