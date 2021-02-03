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

use Flight\Routing\RouteCollection;

/**
 * MatcherDumperInterface is the interface that all matcher dumper classes must implement.
 * The implementation should be writen to receive a Route instances in a array
 * and a file by `constructor` when `dump()` method is used by the router.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
interface MatcherDumperInterface
{
    /**
     * Dumps a set of routes to a string representation of executable code
     * that can then be used to match a request against these routes.
     *
     * @return string Executable code
     */
    public function dump();
}
