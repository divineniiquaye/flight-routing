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

use Psr\Http\Message\ServerRequestInterface;

/**
 * Called by the Router class with information on the route.
 * Provides an opportunity to update the route before it's been executed
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
interface RouteListenerInterface
{
    /**
     * @param ServerRequestInterface $request
     * @param RouteInterface         $route
     */
    public function onRoute(ServerRequestInterface $request, RouteInterface &$route): void;
}
