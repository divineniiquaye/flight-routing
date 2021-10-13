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

namespace Flight\Routing\Tests\Matchers;

use Flight\Routing\Exceptions\UrlGenerationException;
use Flight\Routing\Interfaces\RouteCompilerInterface;
use Flight\Routing\Interfaces\RouteMatcherInterface;
use Flight\Routing\Route;
use Flight\Routing\RouteMatcher;
use Flight\Routing\RouteCollection;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

/**
 * SimpleRouteMatcherTest.
 */
class SimpleRouteMatcherTest extends TestCase
{
    public function testConstructor(): void
    {
        $factory = new RouteMatcher(new RouteCollection());

        $this->assertInstanceOf(RouteMatcherInterface::class, $factory);
    }

    /**
     * @dataProvider routeCompileData
     *
     * @param array<string,int|string|null> $variables
     */
    public function testCompileRoute(string $path, array $variables): void
    {
        $collection = new RouteCollection();
        $collection->add($route = new Route('http://[{lang:[a-z]{2}}.]example.com/{foo}', ['FOO', 'BAR']));

        $factory = new RouteMatcher($collection);
        $route = $factory->matchRequest(new ServerRequest($route->getMethods()[0], $path));

        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals($variables, $route->getArguments());

        $factory = $factory->getCompiler()->compile($route);

        $this->assertEquals('\/(?P<foo>[^\/]+)', $factory[0]);
        $this->assertEquals('(?:(?P<lang>[a-z]{2})\.)?example\.com', $factory[1]);
        $this->assertEquals(['foo' => null, 'lang' => null], $factory[2]);
    }

    /**
     * @dataProvider provideCompileData
     *
     * @param array<string,string> $tokens
     */
    public function testGenerateUri(string $regex, string $match, array $tokens): void
    {
        $collection = new RouteCollection();
        $collection->addRoute($regex, ['FOO', 'BAR'])->bind('test');

        $factory = new RouteMatcher($collection);

        $this->assertEquals($match, (string) $factory->generateUri('test', $tokens));
    }

    public function testGenerateUriNotFound(): void
    {
        $this->expectExceptionMessage('Unable to generate a URL for the named route "something" as such route does not exist.');
        $this->expectException(UrlGenerationException::class);

        $factory = new RouteMatcher(new RouteCollection());
        $factory->generateUri('something');
    }

    public function testGenerateUriWithDefaults(): void
    {
        $collection = new RouteCollection();
        $collection->addRoute('/{foo}', ['FOO', 'BAR'])->bind('test')->default('foo', 'fifty');

        $factory = new RouteMatcher($collection);

        $this->assertEquals('./fifty', $factory->generateUri('test', []));
    }

    public function testRoutesData(): void
    {
        $collection = new RouteCollection();
        $routes = [new Route('/foo'), new Route('/bar'), new Route('baz')];
        $collection->routes($routes);

        $matcher = new RouteMatcher($collection);
        $data = $matcher->getRoutes();

        foreach ($data as $route) {
            $this->assertInstanceOf(Route::class, $route);
        }

        $this->assertCount(3, $data);
    }

    public function testSerializedRoutesData(): void
    {
        $collection = new RouteCollection();
        $routes = [new Route('/foo'), new Route('/bar'), new Route('baz')];
        $collection->routes($routes);

        $matcher = \serialize(new RouteMatcher($collection));
        $data = ($matcher = \unserialize($matcher))->getRoutes();

        foreach ($data as $route) {
            $this->assertInstanceOf(Route::class, $route);
        }

        $this->assertCount(3, $data);
        $this->assertInstanceOf(RouteCompilerInterface::class, $matcher->getCompiler());
    }

    /**
     * @return string[]
     */
    public function routeCompileData(): array
    {
        return [
            ['http://en.example.com/english', ['lang' => 'en', 'foo' => 'english']],
            ['http://example.com/locale', ['lang' => null, 'foo' => 'locale']],
        ];
    }

    public function provideCompileData(): \Generator
    {
        yield 'Build route with variable' => [
            '/{foo}',
            './two',
            ['foo' => 'two'],
        ];

        yield 'Build route with variable and domain' => [
            'http://[{lang:[a-z]{2}}.]example.com/{foo}',
            'http://example.com/cool',
            ['foo' => 'cool'],
        ];

        yield 'Build route with variable and default' => [
            '/{foo=cool}',
            './cool',
            [],
        ];

        yield 'Build route with variable and override default' => [
            '/{foo=cool}',
            './yeah',
            ['foo' => 'yeah'],
        ];
    }
}
