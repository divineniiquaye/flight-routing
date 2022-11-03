<?php declare(strict_types=1);

/*
 * This file is part of Flight Routing.
 *
 * PHP version 8.0 and above required
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 Divine Niiquaye Ibok (https://divinenii.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flight\Routing\Interfaces;

use Flight\Routing\RouteCollection;
use Psr\Http\Message\{ServerRequestInterface, UriInterface};

/**
 * Interface defining required router compiling capabilities.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
interface RouteMatcherInterface
{
    /**
     * Find a route by matching with request method and PSR-7 uri.
     *
     * @return null|array<string,mixed>
     */
    public function match(string $method, UriInterface $uri): ?array;

    /**
     * Find a route by matching with PSR-7 server request.
     *
     * @return null|array<string,mixed>
     */
    public function matchRequest(ServerRequestInterface $request): ?array;

    public function getCollection(): RouteCollection;
}
