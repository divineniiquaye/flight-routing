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

use Flight\Routing\Exceptions\InvalidControllerException;
use Flight\Routing\Interfaces\RouteInterface;
use Flight\Routing\Route;
use PHPUnit\Framework\TestCase;

/**
 * RouteTest
 */
class RouteTest extends TestCase
{
    public function testConstructor(): void
    {
        $routeName           = Fixtures\TestRoute::getTestRouteName();
        $routePath           = Fixtures\TestRoute::getTestRoutePath();
        $routeMethods        = Fixtures\TestRoute::getTestRouteMethods();
        $routeRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();

        $route = new Route(
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
        $this->assertSame([], $route->getDefaults());
        $this->assertSame([], $route->getSchemes());
        $this->assertSame([], $route->getPatterns());
    }

    public function testConstructorWithOptionalParams(): void
    {
        $routeName           = Fixtures\TestRoute::getTestRouteName();
        $routePath           = Fixtures\TestRoute::getTestRoutePath();
        $routeMethods        = Fixtures\TestRoute::getTestRouteMethods();
        $routeRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();
        $routeMiddlewares    = Fixtures\TestRoute::getTestRouteMiddlewares();
        $routeAttributes     = Fixtures\TestRoute::getTestRouteAttributes();

        $route = new Route(
            $routeName,
            $routeMethods,
            $routePath,
            $routeRequestHandler
        );
        $route->addMiddleware(...$routeMiddlewares)
            ->setDomain('https://biurad.com')
            ->setPatterns($routeAttributes)
            ->setDefaults($routeAttributes)
            ->setPatterns($routeAttributes)
            ->setArguments(\array_merge(['hello'], $routeAttributes));

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame('biurad.com', $route->getDomain());
        $this->assertSame($routeMethods, $route->getMethods());
        $this->assertSame($routeRequestHandler, $route->getController());
        $this->assertSame($routeMiddlewares, $route->getMiddlewares());
        $this->assertSame(['https'], $route->getSchemes());
        $this->assertSame($routeAttributes, $route->getPatterns());
        $this->assertSame($routeAttributes, $route->getDefaults());
        $this->assertSame($routeAttributes, $route->getArguments());
    }

    public function testSetName(): void
    {
        $route        = new Fixtures\TestRoute();
        $newRouteName = Fixtures\TestRoute::getTestRouteName();

        $this->assertNotSame($route->getName(), $newRouteName);
        $this->assertSame($route, $route->setName($newRouteName));
        $this->assertSame($newRouteName, $route->getName());
    }

    public function testSerialization(): void
    {
        $routeName           = Fixtures\TestRoute::getTestRouteName();
        $routePath           = Fixtures\TestRoute::getTestRoutePath();
        $routeMethods        = Fixtures\TestRoute::getTestRouteMethods();
        $routeRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();
        $routeMiddlewares    = Fixtures\TestRoute::getTestRouteMiddlewares();
        $routeAttributes     = Fixtures\TestRoute::getTestRouteAttributes();

        $route = new Route(
            $routeName,
            $routeMethods,
            $routePath,
            $routeRequestHandler
        );

        $serialized = $route->serialize();
        $this->assertNull($route->unserialize($serialized));

        $route->addMiddleware(...$routeMiddlewares)
            ->setDomain('https://biurad.com')
            ->setPatterns($routeAttributes)
            ->setDefaults($routeAttributes)
            ->setPatterns($routeAttributes)
            ->setArguments(\array_merge(['hello'], $routeAttributes));

        $route = \serialize($route);

        $this->assertNotInstanceOf(RouteInterface::class, $route);

        $actual = Fixtures\Helper::routesToArray([\unserialize($route)]);

        $this->assertSame([
            'name'        => $routeName,
            'path'        => $routePath,
            'domain'      => 'biurad.com',
            'methods'     => $routeMethods,
            'handler'     => Fixtures\BlankRequestHandler::class,
            'middlewares' => \array_map('get_class', $routeMiddlewares),
            'schemes'     => ['https'],
            'defaults'    => $routeAttributes,
            'patterns'    => $routeAttributes,
            'arguments'   => $routeAttributes,
        ], \current($actual));
    }

    public function testController(): void
    {
        $route                  = new Fixtures\TestRoute();
        $newRouteRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();

        $this->assertNotSame($route->getController(), $newRouteRequestHandler);
    }

    public function testDomainFromPath(): void
    {
        $routeName           = Fixtures\TestRoute::getTestRouteName();
        $routePath           = Fixtures\TestRoute::getTestRoutePath();
        $routeMethods        = Fixtures\TestRoute::getTestRouteMethods();
        $routeRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();

        $route = new Route(
            $routeName,
            $routeMethods,
            '//biurad.com/' . \ltrim($routePath, '/'),
            $routeRequestHandler
        );

        $this->assertEquals($routePath, $route->getPath());
        $this->assertEquals('biurad.com', $route->getDomain());
    }

    public function testSchemeAndADomainFromPath(): void
    {
        $routeName           = Fixtures\TestRoute::getTestRouteName();
        $routePath           = Fixtures\TestRoute::getTestRoutePath();
        $routeMethods        = Fixtures\TestRoute::getTestRouteMethods();
        $routeRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();

        $route = new Route(
            $routeName,
            $routeMethods,
            'https://biurad.com/' . \ltrim($routePath, '/'),
            $routeRequestHandler
        );

        $this->assertEquals($routePath, $route->getPath());
        $this->assertEquals('biurad.com', $route->getDomain());
        $this->assertEquals('https', \current($route->getSchemes()));
    }

    public function testControllerOnNullAndFromPath(): void
    {
        $routeName    = Fixtures\TestRoute::getTestRouteName();
        $routePath    = Fixtures\TestRoute::getTestRoutePath();
        $routeMethods = Fixtures\TestRoute::getTestRouteMethods();

        $route1 = new Route(
            $routeName,
            $routeMethods,
            $routePath . '*<handle>',
            new Fixtures\BlankRequestHandler()
        );
        $route2 = new Route(
            $routeName,
            $routeMethods,
            $routePath . '*<Flight\Routing\Tests\Fixtures\BlankRequestHandler@handle>',
            null
        );

        $this->assertIsCallable($route1->getController());
        $this->assertIsArray($route2->getController());
    }

    public function testExceptionOnPath(): void
    {
        $routeName    = Fixtures\TestRoute::getTestRouteName();
        $routeMethods = Fixtures\TestRoute::getTestRouteMethods();

        $this->expectErrorMessage(
            'Unable to locate route candidate on `*<Flight\Routing\Tests\Fixtures\BlankRequestHandler@handle>`'
        );
        $this->expectException(InvalidControllerException::class);

        $route = new Route(
            $routeName,
            $routeMethods,
            '*<Flight\Routing\Tests\Fixtures\BlankRequestHandler@handle>',
            null
        );
    }

    public function testSetArguments(): void
    {
        $route              = new Fixtures\TestRoute();
        $newRouteAttributes = Fixtures\TestRoute::getTestRouteAttributes();

        $this->assertNotSame($route->getArguments(), $newRouteAttributes);
        $this->assertSame($route, $route->setArguments($newRouteAttributes));
        $this->assertSame($newRouteAttributes, $route->getArguments());
    }

    public function testAddPrefix(): void
    {
        $route        = new Fixtures\TestRoute();
        $pathPrefix   = '/foo';
        $expectedPath = $pathPrefix . $route->getPath();

        $this->assertSame($route, $route->addPrefix($pathPrefix));
        $this->assertSame($expectedPath, $route->getPath());
    }

    public function testAddMethod(): void
    {
        $route           = new Fixtures\TestRoute();
        $extraMethods    = Fixtures\TestRoute::getTestRouteMethods();
        $expectedMethods = \array_merge($route->getMethods(), $extraMethods);

        $this->assertSame($route, $route->addMethod(...$extraMethods));
        $this->assertSame($expectedMethods, $route->getMethods());
    }

    public function testAddMiddleware(): void
    {
        $route               = new Fixtures\TestRoute();
        $extraMiddlewares    = Fixtures\TestRoute::getTestRouteMiddlewares();
        $expectedMiddlewares = \array_merge($route->getMiddlewares(), $extraMiddlewares);

        $this->assertSame($route, $route->addMiddleware(...$extraMiddlewares));
        $this->assertSame($expectedMiddlewares, $route->getMiddlewares());
    }

    public function testSetLowercasedMethods(): void
    {
        $route           = new Route('foo', ['foo', 'bar'], '/', Fixtures\BlankRequestHandler::class);
        $expectedMethods = ['FOO', 'BAR'];

        $this->assertSame($expectedMethods, $route->getMethods());
    }

    public function testAddSlashEndingPrefix(): void
    {
        $route        = new Fixtures\TestRoute();
        $expectedPath = '/foo' . $route->getPath();

        $route->addPrefix('/foo/');
        $this->assertSame($expectedPath, $route->getPath());
    }

    public function testAddLowercasedMethod(): void
    {
        $route             = new Fixtures\TestRoute();
        $expectedMethods   = $route->getMethods();
        $expectedMethods[] = 'GET';
        $expectedMethods[] = 'POST';

        $route->addMethod('get', 'post');
        $this->assertSame($expectedMethods, $route->getMethods());
    }

    public function testPathPrefixWithSymbol(): void
    {
        $route        = new Fixtures\TestRoute();
        $pathPrefix   = 'foo@';
        $expectedPath = $pathPrefix . $route->getPath();

        $this->assertSame($route, $route->addPrefix($pathPrefix));
        $this->assertSame($expectedPath, $route->getPath());
    }
}
