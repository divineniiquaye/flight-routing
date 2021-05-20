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

use Flight\Routing\Exceptions\UriHandlerException;
use Flight\Routing\Matchers\SimpleRouteCompiler;
use Flight\Routing\Route;
use Generator;
use PHPUnit\Framework\TestCase;

/**
 * SimpleRouteCompilerTest
 */
class SimpleRouteCompilerTest extends TestCase
{
    public function testSerialize(): void
    {
        $route = new Route('/prefix/{foo}', ['FOO', 'BAR']);
        $route->default('foo', 'default')
            ->assert('foo', '\d+');

        $compiler = new SimpleRouteCompiler();
        $compiled = $compiler->compile($route);

        $serialized   = \serialize($compiled);
        $unserialized = \unserialize($serialized);

        $this->assertEquals($compiled, $unserialized);
        $this->assertNotSame($compiled, $unserialized);
        $this->assertNotSame($serialized, $compiled->serialize());
        $this->assertNull($compiled->unserialize($compiled->serialize()));
    }

    /**
     * @dataProvider provideCompilePathData
     *
     * @param string               $path
     * @param string[]             $matches
     * @param string               $regex
     * @param array<string,string> $variables
     */
    public function testCompile(string $path, array $matches, string $regex, array $variables = []): void
    {
        $route    = new Route($path, ['FOO', 'BAR']);
        $compiler = new SimpleRouteCompiler();
        $compiled = $compiler->compile($route);

        $this->assertEquals($regex, $compiled->getRegex());
        $this->assertEquals($variables, \array_replace($compiled->getPathVariables(), $route->getDefaults()));

        // Match every pattern...
        foreach ($matches as $match) {
            if (\PHP_VERSION_ID < 70300) {
                $this->assertRegExp($regex, $match);
            } else {
                $this->assertMatchesRegularExpression($regex, $match);
            }
        }
    }

    /**
     * @dataProvider provideCompileHostData
     *
     * @param string               $path
     * @param string[]             $matches
     * @param string               $regex
     * @param array<string,string> $variables
     */
    public function testCompileDomainRegex(string $path, array $matches, string $regex, array $variables = []): void
    {
        $route    = new Route($path, ['FOO', 'BAR']);
        $compiler = new SimpleRouteCompiler();
        $compiled = $compiler->compile($route);

        $this->assertEquals([$regex], $compiled->getHostsRegex());
        $this->assertEquals($variables, \array_merge($compiled->getHostVariables(), $route->getDefaults()));

        // Match every pattern...
        foreach ($matches as $match) {
            if (\PHP_VERSION_ID < 70300) {
                $this->assertRegExp($regex, $match);
            } else {
                $this->assertMatchesRegularExpression($regex, $match);
            }
        }
    }

    /**
     * @dataProvider getInvalidVariableName
     *
     * @param string $variable
     * @param string $exceptionMessage
     */
    public function testCompileVariables(string $variable, string $exceptionMessage): void
    {
        $route = new Route('/{' . $variable . '}', ['FOO', 'BAR']);
        $compiler = new SimpleRouteCompiler();

        $this->expectExceptionMessage(\sprintf($exceptionMessage, $variable));
        $this->expectException(UriHandlerException::class);

        $compiler->compile($route);
    }

    /**
     * @dataProvider getInvalidRequirements
     */
    public function testSetInvalidRequirement(string $req): void
    {
        $this->expectErrorMessage('Routing requirement for "foo" cannot be empty.');
        $this->expectException(UriHandlerException::class);

        $route = new Route('/{foo}', ['FOO', 'BAR']);
        $route->assert('foo', $req);

        $compiler = new SimpleRouteCompiler();
        $compiler->compile($route);
    }

    public function testSameMultipleVariable(): void
    {
        $this->expectErrorMessage('Route pattern "/{foo}{foo}" cannot reference variable name "foo" more than once.');
        $this->expectException(UriHandlerException::class);

        $route = new Route('/{foo}{foo}', ['FOO', 'BAR']);

        $compiler = new SimpleRouteCompiler();
        $compiler->compile($route);
    }

    /**
     * @return string[]
     */
    public function getInvalidVariableName(): array
    {
        return [
            [
                'sfkdfglrjfdgrfhgklfhgjhfdjghrtnhrnktgrelkrngldrjhglhkjdfhgkj',
                'Variable name "%s" cannot be longer than 32 characters in route pattern "/{%1$s}".',
            ],
            [
                '2425',
                'Variable name "%s" cannot start with a digit in route pattern "/{%1$s}". Use a different name.',
            ],
        ];
    }

    /**
     * @return string[]
     */
    public function getInvalidRequirements(): array
    {
        return [
            [''],
            ['^$'],
            ['^'],
            ['$'],
        ];
    }

    /**
     * @return Generator
     */
    public function provideCompilePathData(): Generator
    {
        yield 'Static route' => [
            '/foo',
            ['/foo'],
            '/^\/foo$/sDu',
        ];

        yield 'Route with a variable' => [
            '/foo/{bar}',
            ['/foo/bar'],
            '/^\/foo\/(?P<bar>[^\/]++)$/sDu',
            ['bar' => null],
        ];

        yield 'Route with a variable that has a default value' => [
            '/foo/{bar=<bar>}',
            ['/foo/bar'],
            '/^\/foo\/(?P<bar>[^\/]++)$/sDu',
            ['bar' => 'bar'],
        ];

        yield 'Route with several variables' => [
            '/foo/{bar}/{foobar}',
            ['/foo/bar/baz'],
            '/^\/foo\/(?P<bar>[^\/]+)\/(?P<foobar>[^\/]++)$/sDu',
            ['bar' => null, 'foobar' => null],
        ];

        yield 'Route with several variables that have default values' => [
            '/foo/{bar=<bar>}/{foobar=<0>}',
            ['/foo/foobar/baz'],
            '/^\/foo\/(?P<bar>[^\/]+)\/(?P<foobar>[^\/]++)$/sDu',
            ['bar' => 'bar', 'foobar' => null],
        ];

        yield 'Route with several variables but some of them have no default values' => [
            '/foo/{bar=<bar>}/{foobar}',
            ['/foo/barfoo/baz'],
            '/^\/foo\/(?P<bar>[^\/]+)\/(?P<foobar>[^\/]++)$/sDu',
            ['bar' => 'bar', 'foobar' => null],
        ];

        yield 'Route with an optional variable as the first segment' => [
            '/[{bar}]',
            ['/', 'bar', '/bar'],
            '/^\/?(?:(?P<bar>[^\/]+))?$/sDu',
            ['bar' => null],
        ];

        yield 'Route with an optional variable as the first occurrence' => [
            '[/{foo}]',
            ['/', '/foo'],
            '/^\/?(?:(?P<foo>[^\/]+))?$/sDu',
            ['foo' => null],
        ];

        yield 'Route with an optional variable with inner separator /' => [
            'foo[/{bar}]',
            ['/foo', '/foo/bar'],
            '/^\/foo(?:\/(?P<bar>[^\/]+))?$/sDu',
            ['bar' => null],
        ];

        yield 'Route with a requirement of 0' => [
            '/{bar:0}',
            ['/0'],
            '/^\/(?P<bar>0)$/sDu',
            ['bar' => 0],
        ];

        yield 'Route with a requirement and in optional placeholder' => [
            '/[{lang:[a-z]{2}}/]hello',
            ['/hello', 'hello', '/en/hello', 'en/hello'],
            '/^\/?(?:(?P<lang>[a-z]{2})\/)?hello$/sDu',
            ['lang' => null],
        ];

        yield 'Route with a requirement and in optional placeholder and default' => [
            '/[{lang:lower=<english>}/]hello',
            ['/hello', 'hello', '/en/hello', 'en/hello'],
            '/^\/?(?:(?P<lang>[a-z]+)\/)?hello$/sDu',
            ['lang' => 'english'],
        ];

        yield  'Route with a requirement, optional and required placeholder' => [
            '/[{lang:[a-z]{2}}[-{sublang}]/]{name}[/page-{page=<0>}]',
            ['en-us/foo', '/en-us/foo', 'foo', '/foo', 'en/foo', '/en/foo', 'en-us/foo/page-12', '/en-us/foo/page-12'],
            '/^\/?(?:(?P<lang>[a-z]{2})(?:-(?P<sublang>[^\/]+))?\/)?(?P<name>[^\/]+)(?:\/page-(?P<page>[^\/]++))?$/sDu',
            ['lang' => null, 'sublang' => null, 'name' => null, 'page' => 0],
        ];

        yield 'Route with an optional variable as the first segment with requirements' => [
            '/[{bar:(foo|bar)}]',
            ['/', '/foo', 'bar', 'foo', 'bar'],
            '/^\/?(?:(?P<bar>(foo|bar)))?$/sDu',
            ['bar' => null],
        ];

        yield 'Route with only optional variables with separator /' => [
            '/[{foo}]/[{bar}]',
            ['/', '/foo/', '/foo/bar', 'foo'],
            '/^\/?(?:(?P<foo>[^\/]+))?\/?(?:(?P<bar>[^\/]++))?$/sDu',
            ['foo' => null, 'bar' => null],
        ];

        yield 'Route with only optional variables with inner separator /' => [
            '/[{foo}][/{bar}]',
            ['/', '/foo/bar', 'foo', '/foo'],
            '/^\/?(?:(?P<foo>[^\/]+))?(?:\/(?P<bar>[^\/]++))?$/sDu',
            ['foo' => null, 'bar' => null],
        ];

        yield 'Route with a variable in last position' => [
            '/foo-{bar}',
            ['/foo-bar'],
            '/^\/foo-(?P<bar>[^\/]++)$/sDu',
            ['bar' => null],
        ];

        yield 'Route with a variable and no real seperator' => [
            '/static{var}static',
            ['/staticfoostatic'],
            '/^\/static(?P<var>[^\/]+)static$/sDu',
            ['var' => null],
        ];

        yield 'Route with nested optional paramters' => [
            '/[{foo}/[{bar}]]',
            ['/foo', '/foo', '/foo/', '/foo/bar', 'foo/bar'],
            '/^\/?(?:(?P<foo>[^\/]+)\/?(?:(?P<bar>[^\/]++))?)?$/sDu',
            ['foo' => null, 'bar' => null],
        ];

        yield 'Route with complex matches' => [
            '/hello/{foo:[a-z]{3}=<bar>}{baz}/[{id:[0-9a-fA-F]{1,8}}[.{format:html|php}]]',
            ['/hello/foobar/', '/hello/foobar', '/hello/foobar/0A0AB5', '/hello/foobar/0A0AB5.html'],
            '/^\/hello\/(?P<foo>[a-z]{3})(?P<baz>[^\/]+)\/?(?:(?P<id>[0-9a-fA-F]{1,8})(?:\.(?P<format>html|php))?)?$/sDu',
            ['foo' => 'bar', 'baz' => null, 'id' => null, 'format' => null],
        ];
    }

    /**
     * @return Generator
     */
    public function provideCompileHostData(): Generator
    {
        yield 'Route domain with variable' => [
            '//{foo}.example.com/',
            ['cool.example.com'],
            '/^(?P<foo>[^\/]+)\.example\.com$/sDi',
            ['foo' => null],
        ];

        yield 'Route domain with requirement' => [
            '//{lang:[a-z]{2}}.example.com/',
            ['en.example.com'],
            '/^(?P<lang>[a-z]{2})\.example\.com$/sDi',
            ['lang' => null],
        ];

        yield 'Route with variable at beginning of host' => [
            '//{locale}.example.{tld}/',
            ['en.example.com'],
            '/^(?P<locale>[^\/]+)\.example\.(?P<tld>[^\/]+)$/sDi',
            ['locale' => null, 'tld' => null],
        ];

        yield 'Route domain with requirement and optional variable' => [
            '//[{lang:[a-z]{2}}.]example.com/',
            ['en.example.com', 'example.com'],
            '/^(?:(?P<lang>[a-z]{2})\.)?example\.com$/sDi',
            ['lang' => null],
        ];

        yield 'Route domain with a default requirement on variable and path variable' => [
            '//{id:int}.example.com/',
            ['23.example.com'],
            '/^(?P<id>\d+)\.example\.com$/sDi',
            ['id' => 0],
        ];
    }
}
