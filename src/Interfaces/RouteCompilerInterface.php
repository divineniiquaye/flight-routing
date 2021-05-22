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

use Flight\Routing\CompiledRoute;
use Flight\Routing\Route;

/**
 * This is the interface that all custom compilers for routes will depend on or implement.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
interface RouteCompilerInterface
{
    /**
     * Match the Route instance and compiles the current route instance.
     * 
     * @param bool $reversed The pattern is reversed into a normal url
     */
    public function compile(Route $route, bool $reversed = false): CompiledRoute;
}
