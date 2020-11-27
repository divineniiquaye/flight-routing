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

use Countable;
use IteratorAggregate;

/**
 * Serves as a route storage, instead of using arrays.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
interface RouteCollectionInterface extends IteratorAggregate, Countable
{
    /**
     * Adds the given route(s) to the collection
     *
     * @param RouteInterface ...$routes
     */
    public function add(RouteInterface ...$routes): void;
}
