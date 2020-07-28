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

namespace Flight\Routing\Tests;

use Flight\Routing\Interfaces\RouteCollectionInterface;
use Flight\Routing\RouteCollection;
use PHPUnit\Framework\TestCase;

class RouteCollectionTest extends TestCase
{
    public function testConstructor(): void
    {
        $collection = new RouteCollection();

        $this->assertInstanceOf(RouteCollectionInterface::class, $collection);
    }

    public function testConstructorWithRoutes(): void
    {
        $routes = [
            new Fixtures\TestRoute(),
            new Fixtures\TestRoute(),
            new Fixtures\TestRoute(),
        ];

        $collection = new RouteCollection(...$routes);

        $this->assertCount(3, $collection);
        $this->assertSame($routes, \iterator_to_array($collection));
    }

    public function testAdd(): void
    {
        $routes = [
            new Fixtures\TestRoute(),
            new Fixtures\TestRoute(),
            new Fixtures\TestRoute(),
        ];

        $collection = new RouteCollection();

        $collection->add(...$routes);

        $this->assertCount(3, $collection);
        $this->assertSame($routes, \iterator_to_array($collection));
    }
}
