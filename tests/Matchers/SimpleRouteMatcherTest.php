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

namespace Flight\Routing\Tests\Matchers;

use Flight\Routing\Interfaces\RouteMatcherInterface;
use Flight\Routing\Route;
use Flight\Routing\Matchers\SimpleRouteMatcher;
use Flight\Routing\RouteCollection;
use Generator;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

/**
 * SimpleRouteMatcherTest
 */
class SimpleRouteMatcherTest extends TestCase
{
    public function testConstructor(): void
    {
        $factory = new SimpleRouteMatcher(new RouteCollection());

        $this->assertInstanceOf(RouteMatcherInterface::class, $factory);
    }

    /**
     * @dataProvider routeCompileData
     *
     * @param string $path
     * @param array<string,null|int|string> $variables
     */
    public function testCompileRoute(string $path, array $variables): void
    {
        $collection = new RouteCollection();
        $collection->add($route = new Route('http://[{lang:[a-z]{2}}.]example.com/{foo}', 'FOO|BAR'));

        $factory = new SimpleRouteMatcher($collection);
        $route   = $factory->match(new ServerRequest(array_keys($route->getMethods())[0], $path));

        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals($variables, $route->getDefaults()['_arguments'] ?? []);

        $factory = $factory->getCompiler()->compile($route);

        $this->assertEquals('/^\/(?P<foo>(?U)[^\/]+)$/sDu', $factory->getRegex());
        $this->assertEquals(['/^\/?(?:(?P<lang>(?U)[a-z]{2})\.)?example\.com$/sDi'], $factory->getHostsRegex());
        $this->assertEquals(['foo' => null, 'lang' => null], $factory->getVariables());
    }

    /**
     * @dataProvider provideCompileData
     *
     * @param string               $regex
     * @param string               $match
     * @param array<string,string> $tokens
     */
    public function testBuildPath(string $regex, string $match, array $tokens): void
    {
        $factory = new SimpleRouteMatcher(new RouteCollection());
        $route   = new Route($regex, 'FOO|BAR');

        $this->assertEquals($match, $factory->buildPath($route->bind('test'), $tokens));
    }

    public function testBuildPathWithDefaults(): void
    {
        $factory = new SimpleRouteMatcher(new RouteCollection());
        $route   = new Route('/{foo}', 'FOO|BAR');
        $route->default('foo', 'fifty');

        $this->assertEquals('/fifty', $factory->buildPath($route, []));
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

    /**
     * @return Generator
     */
    public function provideCompileData(): Generator
    {
        yield 'Build route with variable' => [
            '/{foo}',
            '/two',
            ['foo' => 'two'],
        ];

        yield 'Build route with variable position' => [
            '/{foo}',
            '/twelve',
            ['twelve'],
        ];

        yield 'Build route with variable and domain' => [
            'http://[{lang:[a-z]{2}}.]example.com/{foo}',
            'http://example.com/cool',
            ['foo' => 'cool'],
        ];

        yield 'Build route with variable and default' => [
            '/{foo=<cool>}',
            '/cool',
            [],
        ];

        yield 'Build route with variable and override default' => [
            '/{foo=<cool>}',
            '/yeah',
            ['foo' => 'yeah'],
        ];
    }
}
