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
use Flight\Routing\Exceptions\UrlGenerationException;
use Flight\Routing\Generator\GeneratedUri;
use Flight\Routing\Generator\RegexGenerator;
use Flight\Routing\RouteCollection;
use Flight\Routing\RouteCompiler;
use Flight\Routing\Routes\Route;
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

        $compiler = new RouteCompiler();
        $compiled = $compiler->compile($route);

        $serialized   = \serialize($compiled);
        $deserialized = \unserialize($serialized);

        $this->assertEquals($compiled, $deserialized);
        $this->assertSame($compiled, $deserialized);
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
        $compiler = new RouteCompiler();
        $compiled = $compiler->compile($route);

        $this->assertEquals($regex, $compiled[0]);
        $this->assertEquals($variables, $compiled[2] + $route->getDefaults());

        // Match every pattern...
        foreach ($matches as $match) {
            if (\PHP_VERSION_ID < 70300) {
                $this->assertRegExp('/^' . $regex . '$/sDu', $match);
            } else {
                $this->assertMatchesRegularExpression('#^' . $regex . '$#sDu', $match);
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
        $compiler = new RouteCompiler();
        $compiled = $compiler->compile($route);

        $this->assertEquals($regex, $compiled[1]);
        $this->assertEquals($variables, $compiled[2] + $route->getDefaults());

        // Match every pattern...
        foreach ($matches as $match) {
            if (\PHP_VERSION_ID < 70300) {
                $this->assertRegExp('/^' . $regex . '$/sDu', $match);
            } else {
                $this->assertMatchesRegularExpression('#^' . $regex . '$#sDu', $match);
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
        $compiler = new RouteCompiler();

        $this->expectExceptionMessage(\sprintf($exceptionMessage, $variable));
        $this->expectException(UriHandlerException::class);

        $compiler->compile($route);
    }

    public function testCompilerOnRouteCollection(): void
    {
        $compiler = new RouteCompiler();
        $matches = [];
        $actualCount = 0;
        $routes = \array_map(static function (array $values) use (&$matches): Route {
            $matches[$values[2]] = $values[1];

            return new Route($values[0]);
        }, \iterator_to_array($this->provideCompilePathData()));

        $collection = new RouteCollection();
        $collection->add(...$routes);

        foreach ($collection->getRoutes() as $route) {
            $compiledRoute = $compiler->compile($route);

            if (isset($matches[$compiledRoute[0]])) {
                foreach ($matches[$compiledRoute[0]] as $match) {
                    if (1 === \preg_match('#' . $compiledRoute[0] . '#', $match)) {
                        ++$actualCount;
                    }
                }
            }
        }

        $this->assertEquals(62, $actualCount);
    }

    public function testCompilerOnRouteCollectionWithSerialization(): void
    {
        $matches = [];
        $actualCount = 0;
        $routes = \array_map(static function (array $values) use (&$matches): Route {
            foreach ($values[1] as $value) {
                $matches[] = $value;
            }

            return new Route($values[0]);
        }, \iterator_to_array($this->provideCompilePathData()));


        $compiler = new RouteCompiler();
        $collection = new RouteCollection();
        $collection->add(...$routes);

        [$regexList,] = RegexGenerator::beforeCaching($compiler, $collection->getRoutes());

        foreach ($matches as $match) {
            if (1 === \preg_match($regexList, '/' . \ltrim($match, '/'))) {
                ++$actualCount;
            }
        }

        $this->assertEquals(62, $actualCount);
    }

    /**
     * @dataProvider getInvalidRequirements
     */
    public function testSetInvalidRequirement(string $req): void
    {
        $this->expectErrorMessage('Routing requirement for "foo" cannot be empty.');
        $this->expectException(\InvalidArgumentException::class);

        $route = new Route('/{foo}', ['FOO', 'BAR']);
        $route->assert('foo', $req);

        $compiler = new RouteCompiler();
        $compiler->compile($route);
    }

    public function testSameMultipleVariable(): void
    {
        $this->expectErrorMessage('Route pattern "/{foo}{foo}" cannot reference variable name "foo" more than once.');
        $this->expectException(UriHandlerException::class);

        $route = new Route('/{foo}{foo}', ['FOO', 'BAR']);

        $compiler = new RouteCompiler();
        $compiler->compile($route);
    }

    public function testGeneratedUriInstance(): void
    {
        $route1 = new Route('/{foo}');
        $route2 = new Route('/[{bar}]');
        $compiler = new RouteCompiler();

        $this->assertEquals('./hello', (string) $compiler->generateUri($route1, ['foo' => 'hello']));
        $this->assertEquals('./', (string) $compiler->generateUri($route2, []));
        $this->assertEquals('./hello', new GeneratedUri('hello'));
    }

    public function testGeneratedUriInstanceWithHostAndScheme(): void
    {
        $_SERVER['HTTP_HOST'] = 'localhost';
        $route1 = new Route('/{foo}');
        $route2 = Route::to('/[{bar}]')->scheme('https');
        $compiler = new RouteCompiler();

        $generatedUri = $compiler->generateUri($route1, ['foo' => 'hello']);
        $this->assertEquals('biurad.com/hello', (string) $generatedUri->withHost('biurad.com'));

        $this->assertEquals('https://localhost/', (string) $compiler->generateUri($route2, []));
    }

    public function testGeneratedUriWithMandatoryParameter(): void
    {
        $this->expectExceptionMessage('Some mandatory parameters are missing ("foo") to generate a URL for route path "/<foo>".');
        $this->expectException(UrlGenerationException::class);

        $route = new Route('/{foo}');
        (new RouteCompiler())->generateUri($route, []);
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
            ['\A\z'],
            ['\A'],
            ['\z'],
         ];
    }

    /**
     * @return \Generator
     */
    public function provideCompilePathData(): \Generator
    {
        yield 'Static route' => [
            '/foo',
            ['/foo'],
            '\/foo',
        ];

        yield 'Route with a variable' => [
            '/foo/{bar}',
            ['/foo/bar'],
            '\/foo\/(?P<bar>[^\/]+)',
            ['bar' => null],
        ];

        yield 'Route with a variable that has a default value' => [
            '/foo/{bar=bar}',
            ['/foo/bar'],
            '\/foo\/(?P<bar>[^\/]+)',
            ['bar' => 'bar'],
        ];

        yield 'Route with several variables' => [
            '/foo/{bar}/{foobar}',
            ['/foo/bar/baz'],
            '\/foo\/(?P<bar>[^\/]+)\/(?P<foobar>[^\/]+)',
            ['bar' => null, 'foobar' => null],
        ];

        yield 'Route with several variables that have default values' => [
            '/foo/{bar=bar}/{foobar=0}',
            ['/foo/foobar/baz'],
            '\/foo\/(?P<bar>[^\/]+)\/(?P<foobar>[^\/]+)',
            ['bar' => 'bar', 'foobar' => 0],
        ];

        yield 'Route with several variables but some of them have no default values' => [
            '/foo/{bar=bar}/{foobar}',
            ['/foo/barfoo/baz'],
            '\/foo\/(?P<bar>[^\/]+)\/(?P<foobar>[^\/]+)',
            ['bar' => 'bar', 'foobar' => null],
        ];

        yield 'Route with an optional variable as the first segment' => [
            '/[{bar}]',
            ['/', 'bar', '/bar'],
            '\/?(?:(?P<bar>[^\/]+))?',
            ['bar' => null],
        ];

        yield 'Route with an optional variable as the first occurrence' => [
            '[/{foo}]',
            ['/', '/foo'],
            '\/?(?:(?P<foo>[^\/]+))?',
            ['foo' => null],
        ];

        yield 'Route with an optional variable with inner separator /' => [
            'foo[/{bar}]',
            ['/foo', '/foo/bar'],
            '\/foo(?:\/(?P<bar>[^\/]+))?',
            ['bar' => null],
        ];

        yield 'Route with a requirement of 0' => [
            '/{bar:0}',
            ['/0'],
            '\/(?P<bar>0)',
            ['bar' => 0],
        ];

        yield 'Route with a requirement and in optional placeholder' => [
            '/[{lang:[a-z]{2}}/]hello',
            ['/hello', 'hello', '/en/hello', 'en/hello'],
            '\/?(?:(?P<lang>[a-z]{2})\/)?hello',
            ['lang' => null],
        ];

        yield 'Route with a requirement and in optional placeholder and default' => [
            '/[{lang:lower=english}/]hello',
            ['/hello', 'hello', '/en/hello', 'en/hello'],
            '\/?(?:(?P<lang>[a-z]+)\/)?hello',
            ['lang' => 'english'],
        ];

        yield  'Route with a requirement, optional and required placeholder' => [
            '/[{lang:[a-z]{2}}[-{sublang}]/]{name}[/page-{page=0}]',
            ['en-us/foo', '/en-us/foo', 'foo', '/foo', 'en/foo', '/en/foo', 'en-us/foo/page-12', '/en-us/foo/page-12'],
            '\/?(?:(?P<lang>[a-z]{2})(?:-(?P<sublang>[^\/]+))?\/)?(?P<name>[^\/]+)(?:\/page-(?P<page>[^\/]+))?',
            ['lang' => null, 'sublang' => null, 'name' => null, 'page' => 0],
        ];

        yield 'Route with an optional variable as the first segment with requirements' => [
            '/[{bar:(foo|bar)}]',
            ['/', '/foo', 'bar', 'foo', 'bar'],
            '\/?(?:(?P<bar>(foo|bar)))?',
            ['bar' => null],
        ];

        yield 'Route with only optional variables with separator /' => [
            '/[{foo}]/[{bar}]',
            ['/', '/foo/', '/foo/bar', 'foo'],
            '\/?(?:(?P<foo>[^\/]+))?\/?(?:(?P<bar>[^\/]+))?',
            ['foo' => null, 'bar' => null],
        ];

        yield 'Route with only optional variables with inner separator /' => [
            '/[{foo}][/{bar}]',
            ['/', '/foo/bar', 'foo', '/foo'],
            '\/?(?:(?P<foo>[^\/]+))?(?:\/(?P<bar>[^\/]+))?',
            ['foo' => null, 'bar' => null],
        ];

        yield 'Route with a variable in last position' => [
            '/foo-{bar}',
            ['/foo-bar'],
            '\/foo-(?P<bar>[^\/]+)',
            ['bar' => null],
        ];

        yield 'Route with a variable and no real separator' => [
            '/static{var}static',
            ['/staticfoostatic'],
            '\/static(?P<var>[^\/]+)static',
            ['var' => null],
        ];

        yield 'Route with nested optional parameters' => [
            '/[{foo}/[{bar}]]',
            ['/foo', '/foo', '/foo/', '/foo/bar', 'foo/bar'],
            '\/?(?:(?P<foo>[^\/]+)\/?(?:(?P<bar>[^\/]+))?)?',
            ['foo' => null, 'bar' => null],
        ];

        yield 'Route with nested optional parameters 1' => [
            '/[{foo}/{bar}]',
            ['/', '/foo/bar', 'foo/bar'],
            '\/?(?:(?P<foo>[^\/]+)\/(?P<bar>[^\/]+))?',
            ['foo' => null, 'bar' => null],
        ];

        yield 'Route with complex matches' => [
            '/hello/{foo:[a-z]{3}=bar}{baz}/[{id:[0-9a-fA-F]{1,8}}[.{format:html|php}]]',
            ['/hello/foobar/', '/hello/foobar', '/hello/foobar/0A0AB5', '/hello/foobar/0A0AB5.html'],
            '\/hello\/(?P<foo>[a-z]{3})(?P<baz>[^\/]+)\/?(?:(?P<id>[0-9a-fA-F]{1,8})(?:\.(?P<format>html|php))?)?',
            ['foo' => 'bar', 'baz' => null, 'id' => null, 'format' => null],
        ];

        yield 'Route with more complex matches' => [
            '/hello/{foo:\w{3}}{bar=bar1}/world/[{name:[A-Za-z]+}[/{page:int=1}[/{baz:year}]]]/abs.{format:html|php}',
            ['/hello/foo1/world/abs.html', '/hello/bar1/world/divine/abs.php', '/hello/foo1/world/abs.php', '/hello/bar1/world/divine/11/abs.html', '/hello/foo1/world/divine/11/2021/abs.html'],
            '\/hello\/(?P<foo>\w{3})(?P<bar>[^\/]+)\/world\/?(?:(?P<name>[A-Za-z]+)(?:\/(?P<page>\d+)(?:\/(?P<baz>[12][0-9]{3}))?)?)?\/abs\.(?P<format>html|php)',
            ['foo' => null, 'bar' => 'bar1', 'name' => null, 'page' => '1', 'baz' => null, 'format' => null],
        ];
    }

    /**
     * @return \Generator
     */
    public function provideCompileHostData(): \Generator
    {
        yield 'Route domain with variable' => [
            '//{foo}.example.com/',
            ['cool.example.com'],
            '(?P<foo>[^\/]+)\.example\.com',
            ['foo' => null],
        ];

        yield 'Route domain with requirement' => [
            '//{lang:[a-z]{2}}.example.com/',
            ['en.example.com'],
            '(?P<lang>[a-z]{2})\.example\.com',
            ['lang' => null],
        ];

        yield 'Route with variable at beginning of host' => [
            '//{locale}.example.{tld}/',
            ['en.example.com'],
            '(?P<locale>[^\/]+)\.example\.(?P<tld>[^\/]+)',
            ['locale' => null, 'tld' => null],
        ];

        yield 'Route domain with requirement and optional variable' => [
            '//[{lang:[a-z]{2}}.]example.com/',
            ['en.example.com', 'example.com'],
            '(?:(?P<lang>[a-z]{2})\.)?example\.com',
            ['lang' => null],
        ];

        yield 'Route domain with a default requirement on variable and path variable' => [
            '//{id:int}.example.com/',
            ['23.example.com'],
            '(?P<id>\d+)\.example\.com',
            ['id' => 0],
        ];
    }
}
