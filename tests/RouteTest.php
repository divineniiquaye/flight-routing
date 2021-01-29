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

use BadMethodCallException;
use Flight\Routing\Exceptions\InvalidControllerException;
use Flight\Routing\Route;
use PHPUnit\Framework\TestCase;

/**
 * RouteTest
 */
class RouteTest extends TestCase
{
    public function testConstructor(): void
    {
        $testRoute = new Fixtures\TestRoute();
        $route     = new Route(
            $testRoute->getPath(),
            \join('|', \array_keys($testRoute->getMethods())),
            $testRoute->getController()
        );

        $this->assertInstanceOf(Route::class, $route);
        $this->assertSame($testRoute->getPath(), $route->getPath());
        $this->assertSame($testRoute->getMethods(), $route->getMethods());
        $this->assertSame($testRoute->getController(), $route->getController());

        // default property values...
        $this->assertNull($route->getName());
        $this->assertSame([], $route->getMiddlewares());
        $this->assertSame([], $route->getDefaults());
        $this->assertSame([], $route->getSchemes());
        $this->assertSame([], $route->getPatterns());
        $this->assertSame([], $route->getArguments());
        $this->assertNotSame([], $route->getAll());
    }

    public function testSetterMethods(): void
    {
        $testRoute = new Fixtures\TestRoute();
        $route     = new Route(
            $testRoute->getPath(),
            \join('|', \array_keys($testRoute->getMethods())),
            $testRoute->getController()
        );
        $route->bind($testRoute->getName())
            ->middleware(...$testRoute->getMiddlewares())
            ->domain('https://biurad.com')
            ->argument(0, 'hello');

        foreach ($testRoute->getDefaults() as $variable => $default) {
            $route->default($variable, $default);
        }

        foreach ($testRoute->getArguments() as $variable => $value) {
            $route->argument($variable, $value);
        }

        foreach ($testRoute->getPatterns() as $variable => $regexp) {
            $route->assert($variable, $regexp);
        }

        $this->assertSame($testRoute->getName(), $route->getName());
        $this->assertSame($testRoute->getPath(), $route->getPath());
        $this->assertSame(['biurad.com'], \array_keys($route->getDomain()));
        $this->assertSame($testRoute->getMethods(), $route->getMethods());
        $this->assertSame($testRoute->getController(), $route->getController());
        $this->assertSame($testRoute->getMiddlewares(), $route->getMiddlewares());
        $this->assertSame(['https'], \array_keys($route->getSchemes()));
        $this->assertSame($testRoute->getPatterns(), $route->getPatterns());
        $this->assertSame($testRoute->getDefaults(), $route->getDefaults());
        $this->assertSame($testRoute->getArguments(), $route->getArguments());
        $this->assertNotSame($testRoute->getAll(), $route->getAll());
    }

    public function testBind(): void
    {
        $route        = new Fixtures\TestRoute();
        $newRouteName = Fixtures\TestRoute::getTestRouteName();

        $this->assertNotSame($route->getName(), $newRouteName);
        $this->assertSame($route, $route->bind($newRouteName));
        $this->assertSame($newRouteName, $route->getName());
    }

    public function testSerialization(): void
    {
        $testRoute = new Fixtures\TestRoute();
        $route     = new Route(
            $testRoute->getPath(),
            \join('|', \array_keys($testRoute->getMethods())),
            $testRoute->getController()
        );

        $route->bind($testRoute->getName())
            ->middleware(...$testRoute->getMiddlewares())
            ->domain('https://biurad.com')
            ->argument(0, 'hello');

        foreach ($testRoute->getDefaults() as $variable => $default) {
            $route->default($variable, $default);
        }

        foreach ($testRoute->getArguments() as $variable => $value) {
            $route->argument($variable, $value);
        }

        foreach ($testRoute->getPatterns() as $variable => $regexp) {
            $route->assert($variable, $regexp);
        }

        $route = \serialize($route);

        $this->assertNotInstanceOf(Route::class, $route);

        $actual   = Fixtures\Helper::routesToArray([\unserialize($route)]);
        $defaults = $testRoute->getDefaults();
        unset($defaults['_arguments']);

        $this->assertSame([
            'name'        => $testRoute->getName(),
            'path'        => $testRoute->getPath(),
            'domain'      => ['biurad.com'],
            'methods'     => \array_keys($testRoute->getMethods()),
            'handler'     => Fixtures\BlankRequestHandler::class,
            'middlewares' => \array_map('get_class', $testRoute->getMiddlewares()),
            'schemes'     => ['https'],
            'defaults'    => $defaults,
            'patterns'    => $testRoute->getPatterns(),
            'arguments'   => $testRoute->getArguments(),
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
        $testRoute = new Fixtures\TestRoute();
        $route     = new Route(
            '//biurad.com/' . \ltrim($testRoute->getPath(), '/'),
            \join('|', \array_keys($testRoute->getMethods())),
            $testRoute->getController()
        );

        $this->assertEquals($testRoute->getPath(), $route->getPath());
        $this->assertEquals(['biurad.com'], \array_keys($route->getDomain()));
    }

    public function testSchemeAndADomainFromPath(): void
    {
        $testRoute = new Fixtures\TestRoute();
        $route     = new Route(
            'https://biurad.com/' . \ltrim($testRoute->getPath(), '/'),
            \join('|', \array_keys($testRoute->getMethods())),
            $testRoute->getController()
        );

        $this->assertEquals($testRoute->getPath(), $route->getPath());
        $this->assertEquals(['biurad.com'], \array_keys($route->getDomain()));
        $this->assertEquals('https', \current(\array_keys($route->getSchemes())));
    }

    public function testControllerOnNullAndFromPath(): void
    {
        $testRoute = new Fixtures\TestRoute();

        $route1 = new Route(
            $testRoute->getPath() . '*<handle>',
            \join('|', \array_keys($testRoute->getMethods())),
            $testRoute->getController()
        );
        $route2 = new Route(
            $testRoute->getPath() . '*<Flight\Routing\Tests\Fixtures\BlankRequestHandler@handle>',
            \join('|', \array_keys($testRoute->getMethods()))
        );

        $this->assertIsCallable($route1->getController());
        $this->assertIsArray($route2->getController());
        $this->assertEquals([Fixtures\BlankRequestHandler::class, 'handle'], $route2->getController());
    }

    public function testControllerMethodFromPath(): void
    {
        $routeMethods = Fixtures\TestRoute::getTestRouteMethods();
        $route        = new Route('/*<phpinfo>', \join('|', $routeMethods));

        $this->assertIsCallable($route->getController());
        $this->assertEquals('/', $route->getPath());
        $this->assertEquals('phpinfo', $route->getController());
    }

    public function testExceptionOnPath(): void
    {
        $routeMethods = Fixtures\TestRoute::getTestRouteMethods();

        $this->expectErrorMessage('Unable to locate route candidate on `//localhost.com`');
        $this->expectException(InvalidControllerException::class);

        new Route('//localhost.com', \join('|', $routeMethods));
    }

    public function testArgument(): void
    {
        $route              = new Fixtures\TestRoute();
        $newRouteAttributes = Fixtures\TestRoute::getTestRouteAttributes();

        $this->assertNotSame($route->getDefaults()['_arguments'] ?? [], $newRouteAttributes);

        foreach ($newRouteAttributes as $variable => $value) {
            $route->argument($variable, $value);
        }

        $this->assertSame($newRouteAttributes, $route->getArguments());
    }

    public function testPrefix(): void
    {
        $route        = new Fixtures\TestRoute();
        $pathPrefix   = '/foo';
        $expectedPath = $pathPrefix . $route->getPath();

        $this->assertSame($route, $route->prefix($pathPrefix));
        $this->assertSame($expectedPath, $route->getPath());
    }

    public function testAddMethod(): void
    {
        $route           = new Fixtures\TestRoute();
        $extraMethods    = Fixtures\TestRoute::getTestRouteMethods();
        $expectedMethods = \array_merge(\array_keys($route->getMethods()), $extraMethods);

        $this->assertSame($route, $route->method(...$extraMethods));
        $this->assertSame($expectedMethods, \array_keys($route->getMethods()));
    }

    public function testAddMiddleware(): void
    {
        $route               = new Fixtures\TestRoute();
        $extraMiddlewares    = Fixtures\TestRoute::getTestRouteMiddlewares();
        $expectedMiddlewares = \array_merge($route->getMiddlewares(), $extraMiddlewares);

        $this->assertSame($route, $route->middleware(...$extraMiddlewares));
        $this->assertSame($expectedMiddlewares, $route->getMiddlewares());
    }

    public function testSetLowercasedMethods(): void
    {
        $route           = new Route('/', 'foo|bar', Fixtures\BlankRequestHandler::class);
        $expectedMethods = ['FOO', 'BAR'];

        $this->assertSame($expectedMethods, \array_keys($route->getMethods()));
    }

    public function testAddSlashEndingPrefix(): void
    {
        $route        = new Fixtures\TestRoute();
        $expectedPath = '/foo' . $route->getPath();

        $route->prefix('/foo/');
        $this->assertSame($expectedPath, $route->getPath());
    }

    public function testAddLowercasedMethod(): void
    {
        $route             = new Fixtures\TestRoute();
        $expectedMethods   = \array_keys($route->getMethods());
        $expectedMethods[] = 'GET';
        $expectedMethods[] = 'POST';

        $route->method('get', 'post');
        $this->assertSame($expectedMethods, \array_keys($route->getMethods()));
    }

    public function testPathPrefixWithSymbol(): void
    {
        $route        = new Fixtures\TestRoute();
        $pathPrefix   = 'foo@';
        $expectedPath = $pathPrefix . $route->getPath();

        $this->assertSame($route, $route->prefix($pathPrefix));
        $this->assertSame($expectedPath, $route->getPath());
    }

    public function testGetOnNull(): void
    {
        $route = new Fixtures\TestRoute();

        $this->assertNull($route->get('nothing'));
    }

    public function testMethodNotFoundInMagicCall(): void
    {
        $route = new Fixtures\TestRoute();

        $this->expectExceptionMessage(\sprintf(
            'Property "Flight\Routing\Route->exception" does not exist. should be one of [%s],' .
            ' or arguments, prefixed with a \'get\' name; eg: getName().',
            join(', ', \array_keys($route->getAll()))
        ));
        $this->expectException(BadMethodCallException::class);

        $route->exception();
    }

    /**
     * @dataProvider provideRouteAndExpectedRouteName
     */
    public function testDefaultRouteNameGeneration(Route $route, string $prefix, string $expectedRouteName): void
    {
        $route->bind($route->generateRouteName($prefix));

        $this->assertEquals($expectedRouteName, $route->getName());
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
}
