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

/**
 * A fluent interface required by RouteMatcher class.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
interface RouteMapInterface
{
    /**
     * Get the Compiler associated with this class.
     */
    public function getCompiler(): RouteCompilerInterface;

    /**
     * Retuns an array of routes, including it static and dynamic data.
     */
    public function getData(): array;
}
