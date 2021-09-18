<?php

declare(strict_types=1);

/*
 * This file is part of Flight Routing.
 *
 * PHP version 7.4 and above required
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 Biurad Group (https://biurad.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flight\Routing\Tests;

use Flight\Routing\Routes\{DomainRoute, FastRoute, Route};
use Flight\Routing\Exceptions\InvalidControllerException;
use Flight\Routing\Exceptions\UriHandlerException;
use Flight\Routing\Handlers\ResourceHandler;
use PHPUnit\Framework\TestCase;

/**
 * RouteTest.
 */
class RouteTest extends TestCase
{
    public function testConstructor(): void
    {
        $fRoute = new FastRoute('/hello');
        $this->assertInstanceOf(FastRoute::class, $fRoute);

        $dRoute = new DomainRoute('/hello');
        $this->assertInstanceOf(DomainRoute::class, $dRoute);
        $this->assertInstanceOf(FastRoute::class, $dRoute);

        $testRoute = new Route('/hello');
        $this->assertInstanceOf(Route::class, $testRoute);
        $this->assertInstanceOf(FastRoute::class, $testRoute);
        $this->assertInstanceOf(DomainRoute::class, $testRoute);
    }

    public function testStaticToMethod(): void
    {
        $fRoute = FastRoute::to('/hello');
        $this->assertInstanceOf(FastRoute::class, $fRoute);

        $dRoute = DomainRoute::to('/hello');
        $this->assertInstanceOf(DomainRoute::class, $dRoute);
        $this->assertInstanceOf(FastRoute::class, $dRoute);

        $testRoute = Route::to('/hello');
        $this->assertInstanceOf(Route::class, $testRoute);
        $this->assertInstanceOf(FastRoute::class, $testRoute);
        $this->assertInstanceOf(DomainRoute::class, $testRoute);
    }

    public function testSetStateMethod(): void
    {
        $properties = [
            'name' => 'baz',
            'path' => 'hello',
            'methods' => FastRoute::DEFAULT_METHODS,
            'handler' => 'phpinfo',
            'defaults' => ['foo' => 'bar'],
        ];

        $this->assertEquals([
            'name' => 'baz',
            'path' => 'hello',
            'methods' => FastRoute::DEFAULT_METHODS,
            'handler' => 'phpinfo',
            'arguments' => [],
            'defaults' => ['foo' => 'bar'],
            'patterns' => [],
        ], FastRoute::__set_state($properties)->getData());

        $dRoute = DomainRoute::__set_state($properties);
        $this->assertEquals([
            'name' => 'baz',
            'path' => 'hello',
            'methods' => FastRoute::DEFAULT_METHODS,
            'schemes' => [],
            'hosts' => [],
            'handler' => 'phpinfo',
            'arguments' => [],
            'defaults' => ['foo' => 'bar'],
            'patterns' => [],
        ], $dRoute->getData());

        $testRoute = Route::__set_state($properties);
        $this->assertEquals($dRoute->getData(), $testRoute->getData());
    }

    public function testSetStateMethodWihInvalidKey(): void
    {
        $routeData = Route::__set_state(['path' => '/', 'foo' => 'bar'])->getData();

        $this->assertEquals([
            'name' => null,
            'path' => '/',
            'methods' => [],
            'schemes' => [],
            'hosts' => [],
            'handler' => null,
            'arguments' => [],
            'patterns' => [],
            'defaults' => [],
        ], $routeData);
    }

    public function testDomainRoute(): void
    {
        $dRoute1 = new DomainRoute('https://biurad.com/hi');
        $this->assertEquals('/hi', $dRoute1->getPath());
        $this->assertEquals(['https'], $dRoute1->getSchemes());
        $this->assertEquals(['biurad.com'], $dRoute1->getHosts());

        $dRoute2 = new DomainRoute('//biurad.com/hi');
        $this->assertEquals('/hi', $dRoute2->getPath());
        $this->assertEmpty($dRoute2->getSchemes());
        $this->assertEquals(['biurad.com'], $dRoute2->getHosts());

        $dRoute3 = new DomainRoute('/hi');
        $this->assertEquals('/hi', $dRoute3->getPath());
        $this->assertEmpty($dRoute3->getSchemes());
        $this->assertEmpty($dRoute3->getHosts());

        $dRoute4 = DomainRoute::to('//biurad.com/')->path('//localhost/foo');
        $this->assertEquals('/foo', $dRoute4->getPath());
        $this->assertEquals(['biurad.com', 'localhost'], $dRoute4->getHosts());

        $dRoute = DomainRoute::to('https://biurad.com/hi');
        $dRoute->scheme('https')->domain('https://greet.biurad.com', 'biurad.com');

        $this->assertEquals(['https'], $dRoute->getSchemes());
        $this->assertEquals(['biurad.com', 'greet.biurad.com'], $dRoute->getHosts());
    }

    public function testRouteName(): void
    {
        $testRoute = new Route('/foo');
        $testRoute->bind('foo');

        $this->assertEquals('foo', $testRoute->get('name'));
    }

    public function testRouteMethods(): void
    {
        $testRoute1 = new Route('/foo');
        $testRoute2 = new Route('foo', '');
        $testRoute3 = new Route('foo', []);
        $testRoute4 = Route::to('foo', [])->method('connect', 'get', 'get');

        $this->assertEquals(Route::DEFAULT_METHODS, $testRoute1->getMethods());
        $this->assertSame($testRoute2->getMethods(), $testRoute3->getMethods());
        $this->assertEquals(['CONNECT', 'GET'], $testRoute4->getMethods());
    }

    public function testRoutePath(): void
    {
        $staticRoute = new Route('/foo');
        $dynamicRoute = new Route('/hi/{baz}');
        $hostRoute = new Route('//localhost/bar');
        $schemeHostRoute = new Route('ws://localhost:8080/service');

        $this->assertEquals('/foo', $staticRoute->getPath());
        $this->assertEquals('/hi/{baz}', $dynamicRoute->getPath());

        $this->assertEquals('/bar', $hostRoute->getPath());
        $this->assertEquals(['localhost'], $hostRoute->getHosts());

        $this->assertEquals('/service', $schemeHostRoute->getPath());
        $this->assertEquals(['localhost:8080'], $schemeHostRoute->getHosts());
        $this->assertEquals(['ws'], $schemeHostRoute->getSchemes());
    }

    public function testRouteHandler(): void
    {
        $functionRoute = new Route('/foo_1', Route::DEFAULT_METHODS, 'phpinfo');
        $closureRoute = new Route('/foo_2', Route::DEFAULT_METHODS, static function () {
            return 'Hello';
        });
        $invokeRoute = new Route('/foo_3', Route::DEFAULT_METHODS, new Fixtures\InvokeController());
        $requestHandlerRoute = new Route('/foo_4', Route::DEFAULT_METHODS, new Fixtures\BlankRequestHandler());
        $patternRoute1 = new Route('/foo_5*<Flight\Routing\Tests\Fixtures\BlankController@handle>');
        $patternRoute2 = new Route('/foo_6*<handle>', Route::DEFAULT_METHODS, Fixtures\BlankController::class);

        $this->assertIsCallable($functionRoute->getHandler());
        $this->assertIsCallable($closureRoute->getHandler());
        $this->assertIsCallable($invokeRoute->getHandler());
        $this->assertInstanceOf(Fixtures\InvokeController::class, $invokeRoute->getHandler());
        $this->assertInstanceOf(Fixtures\BlankRequestHandler::class, $requestHandlerRoute->getHandler());
        $this->assertEquals([Fixtures\BlankController::class, 'handle'], $patternRoute1->getHandler());
        $this->assertSame($patternRoute1->getHandler(), $patternRoute2->getHandler());
    }

    public function testRouteArguments(): void
    {
        $route = FastRoute::to('/foo')->argument('number', '345')->arguments(['hello' => 'world']);

        $this->assertEquals(['number' => 345, 'hello' => 'world'], $route->getArguments());
    }

    public function testRouteNamespace(): void
    {
        $testRoute1 = Route::to('/foo')->run('\\BlankController')->namespace('Flight\Routing\Tests\Fixtures');
        $testRoute2 = Route::to('/foo')->run('\\Fixtures\BlankController')->namespace('Flight\Routing\Tests');
        $testRoute3 = Route::to('/foo')->run('Fixtures\BlankController')->namespace('Flight\Routing\Tests');
        $testRoute4 = Route::to('/foo')->run(new ResourceHandler('\\Fixtures\BlankRestful', 'user'))->namespace('Flight\Routing\Tests');

        $this->assertSame($testRoute1->getHandler(), $testRoute2->getHandler());
        $this->assertEquals('Fixtures\BlankController', $testRoute3->getHandler());
        $this->assertEquals([Fixtures\BlankRestful::class, 'getUser'], $testRoute4->getHandler()('GET'));

        $this->expectExceptionMessage('Namespace "Flight\Routing\Tests\" provided for routes must not end with a "\".');
        $this->expectException(InvalidControllerException::class);

        Route::to('/foo')->run('Fixtures\BlankController')->namespace('Flight\Routing\Tests\\');
    }

    /**
     * @dataProvider providePrefixAndExpectedNewPath
     */
    public function testRoutePrefix(string $path, string $prefix, string $expected): void
    {
        $testRoute = new Route($path);
        $testRoute->prefix($prefix);

        $this->assertSame($expected, $testRoute->getPath());
    }

    public function testMethodNotFoundInMagicCall(): void
    {
        $route = new Route('/foo');

        $this->expectExceptionMessage('Invalid call for "exception" in Flight\Routing\Routes\FastRoute::get(\'exception\'), try any of [name,path,methods,schemes,hosts,handler,arguments,patterns,defaults].');
        $this->expectException(\InvalidArgumentException::class);

        $route->exception();
    }

    public function testNotAllowedEmptyPath(): void
    {
        $this->expectExceptionMessage('The route pattern "" is invalid as route path must be present in pattern.');
        $this->expectException(UriHandlerException::class);

        $route = new Route('');
    }

    public function testNotAllowedEmptyPathInHost(): void
    {
        $this->expectExceptionMessage('The route pattern "//localhost" is invalid as route path must be present in pattern.');
        $this->expectException(UriHandlerException::class);

        new Route('//localhost');
    }

    public function testNotAllowedEmptyPathInHostAndScheme(): void
    {
        $this->expectExceptionMessage('The route pattern "http://biurad.com" is invalid as route path must be present in pattern.');
        $this->expectException(UriHandlerException::class);

        new Route('http://biurad.com');
    }

    /**
     * @dataProvider provideRouteAndExpectedRouteName
     */
    public function testDefaultRouteNameGeneration(Route $route, string $prefix, string $expectedRouteName): void
    {
        $route->bind($route->generateRouteName($prefix));

        $this->assertEquals($expectedRouteName, $route->getName());
    }

    public function testRoutePipeline(): void
    {
        $route = new Route('/foo');
        $route->piped('web');

        $this->assertEquals(['web'], $route->getPiped());
    }

    /**
     * @return string[]
     */
    public function provideRouteAndExpectedRouteName(): array
    {
        return [
            [new Route('/Invalid%Symbols#Stripped', 'POST'), '', 'POST_InvalidSymbolsStripped'],
            [new Route('/post/{id}', 'GET'), '', 'GET_post_id'],
            [new Route('/colon:pipe|dashes-escaped', ''), '', '_colon_pipe_dashes_escaped'],
            [new Route('/underscores_and.periods', ''), '', '_underscores_and.periods'],
            [new Route('/post/{id}', 'GET'), 'prefix', 'GET_prefix_post_id'],
        ];
    }

    /**
     * @return string[]
     */
    public function providePrefixAndExpectedNewPath(): array
    {
        return [
            ['/foo', '/bar', '/bar/foo'],
            ['/foo', '/bar/', '/bar/foo'],
            ['/foo', 'bar', 'bar/foo'],
            ['foo', '/bar', '/bar/foo'],
            ['foo', 'bar', 'bar/foo'],
            ['@foo', 'bar', 'bar@foo'],
            ['foo', '@bar', '@bar/foo'],
            ['~foo', '/bar~', '/bar~foo'],
            ['/foo', '', '/foo'],
            ['foo', '', 'foo'],
            ['/foo', 'bar_', 'bar_foo'],
        ];
    }
}
