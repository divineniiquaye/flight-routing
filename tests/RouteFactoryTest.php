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

use Flight\Routing\Interfaces\RouteFactoryInterface;
use Flight\Routing\Interfaces\RouteInterface;
use Flight\Routing\RouteFactory;
use PHPUnit\Framework\TestCase;

/**
 * RouteFactoryTest
 */
class RouteFactoryTest extends TestCase
{

    /**
     * @return void
     */
    public function testConstructor() : void
    {
        $factory = new RouteFactory();

        $this->assertInstanceOf(RouteFactoryInterface::class, $factory);
    }

    /**
     * @return void
     */
    public function testCreateRoute() : void
    {
        $routeName = Fixtures\TestRoute::getTestRouteName();
        $routePath = Fixtures\TestRoute::getTestRoutePath();
        $routeMethods = Fixtures\TestRoute::getTestRouteMethods();
        $routeRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();

        $route = (new RouteFactory)->createRoute(
            $routeName,
            $routeMethods,
            $routePath,
            $routeRequestHandler
        );

        $this->assertInstanceOf(RouteInterface::class, $route);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame($routeMethods, $route->getMethods());
        $this->assertSame($routeRequestHandler, $route->getController());

        // default property values...
        $this->assertSame([], $route->getMiddlewares());
        $this->assertSame([], $route->getArguments());
    }

    /**
     * @return void
     */
    public function testCreateRouteWithOptionalParams() : void
    {
        $routeName = Fixtures\TestRoute::getTestRouteName();
        $routePath = Fixtures\TestRoute::getTestRoutePath();
        $routeMethods = Fixtures\TestRoute::getTestRouteMethods();
        $routeRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();
        $routeMiddlewares = Fixtures\TestRoute::getTestRouteMiddlewares();
        $routeAttributes = Fixtures\TestRoute::getTestRouteAttributes();

        $route = (new RouteFactory)->createRoute(
            $routeName,
            $routeMethods,
            $routePath,
            $routeRequestHandler
        )->addMiddleware(...$routeMiddlewares)->setArguments($routeAttributes);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame($routeMethods, $route->getMethods());
        $this->assertSame($routeRequestHandler, $route->getController());
        $this->assertSame($routeMiddlewares, $route->getMiddlewares());
        $this->assertSame($routeAttributes, $route->getArguments());
    }
}
