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

use Flight\Routing\Interfaces\RouteInterface;
use Flight\Routing\Interfaces\RouteMatcherInterface;
use Flight\Routing\Route;
use Flight\Routing\Matchers\SimpleRouteMatcher;
use Flight\Routing\RouteList;
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
        $factory = new SimpleRouteMatcher();

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
        $factory = new SimpleRouteMatcher();

        $collection = new RouteList();
        $collection->add($route = new Route('test', ['FOO', 'BAR'], 'http://[{lang:[a-z]{2}}.]example.com/{foo}', null));
        $route = $factory->match($collection, new ServerRequest($route->getMethods()[0], $path));

        $this->assertInstanceOf(RouteInterface::class, $route);
        $this->assertEquals($variables, $route->getArguments());

        $factory = $factory->getCompiler()->compile($route);

        $this->assertEquals('/^\/(?P<foo>(?U)[^\/]+)$/sDu', $factory->getRegex());
        $this->assertEquals('/^\/?(?:(?P<lang>(?U)[a-z]{2})\.)?example\.com$/sDi', $factory->getHostRegex());
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
        $factory = new SimpleRouteMatcher();
        $route   = new Route('test', ['FOO', 'BAR'], $regex, null);

        $this->assertEquals($match, $factory->buildPath($route, $tokens));
    }

    public function testBuildPathWithDefaults(): void
    {
        $factory = new SimpleRouteMatcher();
        $route   = new Route('test', ['FOO', 'BAR'], '/{foo}', null);
        $route->setDefaults(['foo' => 'fifty']);

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
