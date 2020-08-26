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

namespace Flight\Routing\Tests\Services;

use Flight\Routing\Exceptions\UriHandlerException;
use Flight\Routing\Route;
use Flight\Routing\Services\SimpleRouteCompiler;
use Generator;
use PHPUnit\Framework\TestCase;

/**
 * SimpleRouteCompilerTest
 */
class SimpleRouteCompilerTest extends TestCase
{
    public function testSerialize(): void
    {
        $route = new Route('test_compile', ['FOO', 'BAR'], '/prefix/{foo}', null);
        $route->setDefaults(['foo' => 'default'])
            ->addPattern('foo', '\d+');

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
        $route    = new Route('test_compile', ['FOO', 'BAR'], $path, null);
        $compiler = new SimpleRouteCompiler();
        $compiled = $compiler->compile($route);

        $this->assertEquals($regex, $compiled->getRegex());
        $this->assertEquals($variables, \array_merge($compiled->getPathVariables(), $route->getDefaults()));

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
        $route    = new Route('test_compile', ['FOO', 'BAR'], $path, null);
        $compiler = new SimpleRouteCompiler();
        $compiled = $compiler->compile($route);

        $this->assertEquals($regex, $compiled->getHostRegex());
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

    public function testCompileWithMaxVariable(): void
    {
        $variable = 'sfkdfglrjfdgrfhgklfhgjhfdjghrtnhrnktgrelkrngldrjhglhkjdfhgkj';
        $route    = new Route(
            'test_compile',
            ['FOO', 'BAR'],
            '/{' . $variable . '}',
            null
        );
        $compiler = new SimpleRouteCompiler();

        $this->expectExceptionMessage(
            \sprintf(
                'Variable name "%s" cannot be longer than 32 characters in route pattern "/{%s}".',
                $variable,
                $variable
            )
        );
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

        $route = new Route('test_compile', ['FOO', 'BAR'], '/{foo}', null);
        $route->addPattern('foo', $req);

        $compiler = new SimpleRouteCompiler();
        $compiled = $compiler->compile($route);

        $compiled->getRegex();
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
            ['/foo', 'foo'],
            '#^/?foo$#sD',
        ];

        yield 'Route with a variable' => [
            '/foo/{bar}',
            ['/foo/bar', 'foo/bar'],
            '#^/?foo\/(?P<bar>(?U)[^\/]+)$#sD',
            ['bar' => null],
        ];

        yield 'Route with a variable that has a default value' => [
            '/foo/{bar=<bar>}',
            ['/foo/bar', 'foo/bar'],
            '#^/?foo\/(?P<bar>(?U)[^\/]+)$#sD',
            ['bar' => 'bar'],
        ];

        yield 'Route with several variables' => [
            '/foo/{bar}/{foobar}',
            ['/foo/bar/baz', 'foo/bar/baz'],
            '#^/?foo\/(?P<bar>(?U)[^\/]+)\/(?P<foobar>(?U)[^\/]+)$#sD',
            ['bar' => null, 'foobar' => null],
        ];

        yield 'Route with several variables that have default values' => [
            '/foo/{bar=<bar>}/{foobar=<0>}',
            ['/foo/bar/baz', 'foo/bar/baz'],
            '#^/?foo\/(?P<bar>(?U)[^\/]+)\/(?P<foobar>(?U)[^\/]+)$#sD',
            ['bar' => 'bar', 'foobar' => '0'],
        ];

        yield 'Route with several variables but some of them have no default values' => [
            '/foo/{bar=<bar>}/{foobar}',
            ['/foo/bar/baz', 'foo/bar/baz'],
            '#^/?foo\/(?P<bar>(?U)[^\/]+)\/(?P<foobar>(?U)[^\/]+)$#sD',
            ['bar' => 'bar', 'foobar' => null],
        ];

        yield 'Route with an optional variable as the first segment' => [
            '/[{bar}]',
            ['/', 'bar', '/bar'],
            '#^/?(?:(?P<bar>(?U)[^\/]+))?$#sD',
            ['bar' => null],
        ];

        yield 'Route with an optional variable with inner separator /' => [
            '[/{bar}]',
            ['bar'],
            '#^(?:(?P<bar>(?U)[^\/]+))?$#sD',
            ['bar' => null],
        ];

        yield 'Route with a requirement of 0' => [
            '/{bar:0}',
            ['/0', '0'],
            '#^/?(?P<bar>(?U)0)$#sD',
            ['bar' => 0],
        ];

        yield 'Route with a requirement and in optional placeholder' => [
            '/[{lang:[a-z]{2}}/]hello',
            ['/hello', 'hello', '/en/hello', 'en/hello'],
            '#^/?(?:(?P<lang>(?U)[a-z]{2})\/)?hello$#sD',
            ['lang' => null],
        ];

        yield  'Route with a requirement, optional and required placeholder' => [
            '/[{lang:[a-z]{2}}[-{sublang}]/]{name}[/page-{page=<0>}]',
            ['en-us/foo', '/en-us/foo', 'foo', '/foo', 'en/foo', '/en/foo', 'en-us/foo/page-12', '/en-us/foo/page-12'],
            '#^/?(?:(?P<lang>(?U)[a-z]{2})(?:-(?P<sublang>(?U)[^\/]+))?\/)?(?P<name>(?U)[^\/]+)(?:\/page-(?P<page>(?U)[^\/]+))?$#sD',
            ['lang' => null, 'sublang' => null, 'name' => null, 'page' => 0],
        ];

        yield 'Route with an optional variable as the first segment with requirements' => [
            '/[{bar:(foo|bar)}]',
            ['/', '/foo', 'bar', 'foo', 'bar'],
            '#^/?(?:(?P<bar>(?U)(foo|bar)))?$#sD',
            ['bar' => null],
        ];

        yield 'Route with only optional variables with separator /' => [
            '/[{foo}]/[{bar}]',
            ['/', '/foo/', '/foo/bar', 'foo'],
            '#^/?(?:(?P<foo>(?U)[^\/]+))?/?(?:(?P<bar>(?U)[^\/]+))?$#sD',
            ['foo' => null, 'bar' => null],
        ];

        yield 'Route with only optional variables with inner separator /' => [
            '/[{foo}][/{bar}]',
            ['/', '/foo/bar', 'foo'],
            '#^/?(?:(?P<foo>(?U)[^\/]+))?(?:\/(?P<bar>(?U)[^\/]+))?$#sD',
            ['foo' => null, 'bar' => null],
        ];

        yield 'Route with a variable in last position' => [
            '/foo-{bar}',
            ['/foo-bar', 'foo-bar'],
            '#^/?foo-(?P<bar>(?U)[^\/]+)$#sD',
            ['bar' => null],
        ];

        yield 'Route with nested placeholders' => [
            '/{static{var}static}',
            ['/staticfoostatic', 'staticfoostatic'],
            '#^/?static(?P<var>(?U)[^\/]+)static$#sD',
            ['var' => null],
        ];

        yield 'Route with nested optional paramters' => [
            '/[{foo}/[{bar}]]',
            ['/foo', '/foo', '/foo/', '/foo/bar', 'foo/bar'],
            '#^/?(?:(?P<foo>(?U)[^\/]+)/?(?:(?P<bar>(?U)[^\/]+))?)?$#sD',
            ['foo' => null, 'bar' => null],
        ];
    }

    /**
     * @return Generator
     */
    public function provideCompileHostData(): Generator
    {
        yield 'Route domain with variable' => [
            '//{foo}.example.com',
            ['cool.example.com'],
            '#^(?P<foo>(?U)[^\/]+)\.example\.com$#sDi',
            ['foo' => null],
        ];

        yield 'Route domain with requirement' => [
            '//{lang:[a-z]{2}}.example.com',
            ['en.example.com'],
            '#^(?P<lang>(?U)[a-z]{2})\.example\.com$#sDi',
            ['lang' => null],
        ];

        yield 'Route with variable at beginning of host' => [
            '//{locale}.example.{tld}',
            ['en.example.com'],
            '#^(?P<locale>(?U)[^\/]+)\.example\.(?P<tld>(?U)[^\/]+)$#sDi',
            ['locale' => null, 'tld' => null],
        ];

        yield 'Route domain with requirement and optional variable' => [
            '//[{lang:[a-z]{2}}.]example.com',
            ['en.example.com', 'example.com'],
            '#^(?:(?P<lang>(?U)[a-z]{2})\.)?example\.com$#sDi',
            ['lang' => null],
        ];

        yield 'Route domain with a default requirement on variable and path variable' => [
            '//{id:int}.example.com',
            ['23.example.com'],
            '#^(?P<id>(?U)\d+)\.example\.com$#sDi',
            ['id' => 0],
        ];
    }
}
