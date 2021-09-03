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

namespace Flight\Routing\Tests\Annotation;

use Flight\Routing\Annotation\Route;
use Flight\Routing\Exceptions\UriHandlerException;
use PHPUnit\Framework\TestCase;

/**
 * RouteTest.
 */
class RouteTest extends TestCase
{
    public function testConstructor(): void
    {
        $params = [
            'name' => 'foo',
            'path' => '/foo',
            'methods' => ['GET'],
        ];

        $route = new Route($params['path'], $params['name'], $params['methods']);

        $this->assertSame($params['name'], $route->name);
        $this->assertSame($params['path'], $route->path);
        $this->assertSame($params['methods'], $route->methods);

        // default property values...
        $this->assertSame([], $route->defaults);
        $this->assertSame([], $route->patterns);
        $this->assertSame([], $route->schemes);
        $this->assertSame([], $route->hosts);
    }

    public function testExportToRoute(): void
    {
        $params = [
            'name' => 'foo',
            'path' => '/foo',
            'methods' => ['GET'],
        ];

        $route = new Route($params['path'], $params['name'], $params['methods']);
        $routeData = $route->getRoute(null)->getData();

        $this->assertEquals($params['name'], $routeData['name']);
        $this->assertEquals($params['path'], $routeData['path']);
        $this->assertEquals($params['methods'], $routeData['methods']);
    }

    public function testInvalidPath(): void
    {
        $this->expectExceptionObject(new UriHandlerException('A route path not could not be found, Did you forget include one.'));

        $route = new Route();
        $route->getRoute(null);
    }
}
