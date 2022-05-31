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

use Flight\Routing\Exceptions\MethodNotAllowedException;
use Flight\Routing\Exceptions\UriHandlerException;
use Flight\Routing\Exceptions\UrlGenerationException;
use Flight\Routing\Generator\GeneratedUri;
use Flight\Routing\Interfaces\RouteCompilerInterface;
use Flight\Routing\Interfaces\RouteMatcherInterface;
use Flight\Routing\Route;
use Flight\Routing\RouteMatcher;
use Flight\Routing\RouteCollection;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Uri;
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

        $this->assertEquals('{^\/(?P<foo>[^\/]+)?$}u', $factory[0]);
        $this->assertEquals('{^(?:(?P<lang>[a-z]{2})\.)?example\.com$}ui', $factory[1]);
        $this->assertEquals(['foo' => null, 'lang' => null], $factory[2]);
    }

    public function testSamePathOnMethodMatch(): void
    {
        $collection = new RouteCollection();
        $route1 = $collection->addRoute('/foo', Route::DEFAULT_METHODS)->getRoute();
        $route2 = $collection->addRoute('/foo', ['POST'])->getRoute();
        $route3 = $collection->addRoute('/bar/{var}', Route::DEFAULT_METHODS)->getRoute();
        $route4 = $collection->addRoute('/bar/{var}', ['POST'])->getRoute();

        $matcher = new RouteMatcher($collection);
        $this->assertSame($route1, $matcher->match('GET', new Uri('/foo')));
        $this->assertSame($route2, $matcher->match('POST', new Uri('/foo')));
        $this->assertSame($route3, $matcher->match('GET', new Uri('/bar/foo')));
        $this->assertSame($route4, $matcher->match('POST', new Uri('/bar/foo')));

        $serializedMatcher = \unserialize(\serialize($matcher));
        $this->assertInstanceOf(RouteMatcherInterface::class, $serializedMatcher);

        $this->assertEquals($route1->getData(), $serializedMatcher->match('GET', new Uri('/foo'))->getData());
        $this->assertEquals($route2->getData(), $serializedMatcher->match('POST', new Uri('/foo'))->getData());
        $this->assertEquals($route3->getData(), $serializedMatcher->match('GET', new Uri('/bar/foo'))->getData());

        $this->expectExceptionMessage('Route with "/bar/foo" path is allowed on request method(s) [GET], "POST" is invalid.');
        $this->expectException(MethodNotAllowedException::class);
        $serializedMatcher->match('POST', new Uri('/bar/foo'));
    }

    public function testMatchSamePathWithInvalidMethod(): void
    {
        $collection = new RouteCollection();
        $collection->addRoute('/foo', Route::DEFAULT_METHODS);
        $collection->addRoute('/foo', ['POST']);
        $matcher = new RouteMatcher($collection);

        $this->expectExceptionMessage('Route with "/foo" path is allowed on request method(s) [GET,POST], "PATCH" is invalid.');
        $this->expectException(MethodNotAllowedException::class);
        $matcher->match('PATCH', new Uri('/foo'));
    }

    public function testMatchSamePathWithInvalidMethodAndSerializedMatcher(): void
    {
        $collection = new RouteCollection();
        $collection->addRoute('/foo', Route::DEFAULT_METHODS);
        $collection->addRoute('/foo', ['POST']);
        $matcher = \unserialize(\serialize(new RouteMatcher($collection)));

        $this->expectExceptionMessage('Route with "/foo" path is allowed on request method(s) [GET,POST], "PATCH" is invalid.');
        $this->expectException(MethodNotAllowedException::class);
        $matcher->match('PATCH', new Uri('/foo'));
    }

    public function testSamePathOnDomainMatch(): void
    {
        $collection = new RouteCollection();
        $route1 = $collection->addRoute('/foo', Route::DEFAULT_METHODS)->domain('localhost')->getRoute();
        $route2 = $collection->addRoute('/foo', Route::DEFAULT_METHODS)->domain('biurad.com')->getRoute();
        $route3 = $collection->addRoute('/bar/{var}', Route::DEFAULT_METHODS)->domain('localhost')->getRoute();
        $route4 = $collection->addRoute('/bar/{var}', Route::DEFAULT_METHODS)->domain('biurad.com')->getRoute();

        $matcher = new RouteMatcher($collection);
        $this->assertSame($route1, $matcher->match('GET', new Uri('//localhost/foo')));
        $this->assertSame($route2, $matcher->match('GET', new Uri('//biurad.com/foo')));
        $this->assertSame($route3, $matcher->match('GET', new Uri('//localhost/bar/foo')));
        $this->assertSame($route4, $matcher->match('GET', new Uri('//biurad.com/bar/foo')));

        $serializedMatcher = \unserialize(\serialize($matcher));
        $this->assertInstanceOf(RouteMatcherInterface::class, $serializedMatcher);

        $this->assertEquals($route1->getData(), $serializedMatcher->match('GET', new Uri('//localhost/foo'))->getData());
        $this->assertEquals($route2->getData(), $serializedMatcher->match('GET', new Uri('//biurad.com/foo'))->getData());
        $this->assertEquals($route3->getData(), $serializedMatcher->match('GET', new Uri('//localhost/bar/foo'))->getData());

        $this->expectExceptionMessage('Route with "/bar/foo" path is not allowed on requested uri "//biurad.com/bar/foo" as uri host is invalid.');
        $this->expectException(UriHandlerException::class);
        $serializedMatcher->match('GET', new Uri('//biurad.com/bar/foo'));
    }

    public function testMatchSamePathWithInvalidDomain(): void
    {
        $collection = new RouteCollection();
        $collection->addRoute('/foo', Route::DEFAULT_METHODS)->domain('localhost');
        $collection->addRoute('/foo', Route::DEFAULT_METHODS)->domain('biurad.com');
        $matcher = new RouteMatcher($collection);

        $this->expectExceptionMessage('Route with "/foo" path is not allowed on requested uri "//localhost.com/foo" as uri host is invalid.');
        $this->expectException(UriHandlerException::class);
        $matcher->match('GET', new Uri('//localhost.com/foo'));
    }

    public function testMatchSamePathWithInvalidDomainAndSerializedMatcher(): void
    {
        $collection = new RouteCollection();
        $collection->addRoute('/foo', Route::DEFAULT_METHODS)->domain('localhost');
        $collection->addRoute('/foo', Route::DEFAULT_METHODS)->domain('biurad.com');
        $matcher = \unserialize(\serialize(new RouteMatcher($collection)));

        $this->expectExceptionMessage('Route with "/foo" path is not allowed on requested uri "//localhost.com/foo" as uri host is invalid.');
        $this->expectException(UriHandlerException::class);
        $matcher->match('GET', new Uri('//localhost.com/foo'));
    }

    public function testSamePathOnSchemeMatch(): void
    {
        $collection = new RouteCollection();
        $route1 = $collection->addRoute('/foo', Route::DEFAULT_METHODS)->scheme('https')->getRoute();
        $route2 = $collection->addRoute('/foo', Route::DEFAULT_METHODS)->scheme('http')->getRoute();
        $route3 = $collection->addRoute('/bar/{var}', Route::DEFAULT_METHODS)->scheme('https')->getRoute();
        $route4 = $collection->addRoute('/bar/{var}', Route::DEFAULT_METHODS)->scheme('http')->getRoute();

        $matcher = new RouteMatcher($collection);
        $this->assertSame($route1, $matcher->match('GET', new Uri('https://localhost/foo')));
        $this->assertSame($route2, $matcher->match('GET', new Uri('http://localhost/foo')));
        $this->assertSame($route3, $matcher->match('GET', new Uri('https://localhost/bar/foo')));
        $this->assertSame($route4, $matcher->match('GET', new Uri('http://localhost/bar/foo')));

        $serializedMatcher = \unserialize(\serialize($matcher));
        $this->assertInstanceOf(RouteMatcherInterface::class, $serializedMatcher);

        $this->assertEquals($route1->getData(), $serializedMatcher->match('GET', new Uri('https://localhost/foo'))->getData());
        $this->assertEquals($route2->getData(), $serializedMatcher->match('GET', new Uri('http://localhost/foo'))->getData());
        $this->assertEquals($route3->getData(), $serializedMatcher->match('GET', new Uri('https://localhost/bar/foo'))->getData());

        $this->expectExceptionMessage('Route with "/bar/foo" path is not allowed on requested uri "http://localhost/bar/foo" with invalid scheme, supported scheme(s): [https].');
        $this->expectException(UriHandlerException::class);
        $serializedMatcher->match('GET', new Uri('http://localhost/bar/foo'));
    }

    public function testMatchSamePathWithInvalidScheme(): void
    {
        $collection = new RouteCollection();
        $collection->addRoute('/foo', Route::DEFAULT_METHODS)->scheme('https');
        $collection->addRoute('/foo', Route::DEFAULT_METHODS)->scheme('http');
        $matcher = new RouteMatcher($collection);

        $this->expectExceptionMessage('Route with "/foo" path is not allowed on requested uri "ftp://localhost.com/foo" with invalid scheme, supported scheme(s): [https, http].');
        $this->expectException(UriHandlerException::class);
        $matcher->match('GET', new Uri('ftp://localhost.com/foo'));
    }

    public function testMatchSamePathWithInvalidSchemeAndSerializedMatcher(): void
    {
        $collection = new RouteCollection();
        $collection->addRoute('/foo', Route::DEFAULT_METHODS)->domain('localhost');
        $collection->addRoute('/foo', Route::DEFAULT_METHODS)->domain('biurad.com');
        $matcher = \unserialize(\serialize(new RouteMatcher($collection)));

        $this->expectExceptionMessage('Route with "/foo" path is not allowed on requested uri "//localhost.com/foo" as uri host is invalid.');
        $this->expectException(UriHandlerException::class);
        $matcher->match('GET', new Uri('//localhost.com/foo'));
    }

    public function testMatchingRouteWithEndingSlash(): void
    {
        $collection = new RouteCollection();
        $route1 = $collection->addRoute('/foo/', Route::DEFAULT_METHODS)->getRoute();
        $route2 = $collection->addRoute('/bar@', Route::DEFAULT_METHODS)->getRoute();
        $route3 = $collection->addRoute('/foo/{var}/', Route::DEFAULT_METHODS)->getRoute();
        $route4 = $collection->addRoute('/bar/{var:[a-z]{3}}@', Route::DEFAULT_METHODS)->getRoute();

        $matcher = new RouteMatcher($collection);
        $this->assertSame($route1, $matcher->match('GET', new Uri('/foo')));
        $this->assertSame($route1, $matcher->match('GET', new Uri('/foo/')));
        $this->assertSame($route2, $matcher->match('GET', new Uri('/bar')));
        $this->assertSame($route2, $matcher->match('GET', new Uri('/bar@')));
        $this->assertSame($route3, $matcher->match('GET', new Uri('/foo/bar')));
        $this->assertSame($route3, $matcher->match('GET', new Uri('/foo/bar/')));
        $this->assertSame($route4, $matcher->match('GET', new Uri('/bar/foo')));
        $this->assertSame($route4, $matcher->match('GET', new Uri('/bar/foo@')));

        $serializedMatcher = \unserialize(\serialize($matcher));
        $this->assertInstanceOf(RouteMatcherInterface::class, $serializedMatcher);

        $this->assertEquals($route1->getData(), $serializedMatcher->match('GET', new Uri('/foo'))->getData());
        $this->assertEquals($route1->getData(), $serializedMatcher->match('GET', new Uri('/foo/'))->getData());
        $this->assertEquals($route2->getData(), $serializedMatcher->match('GET', new Uri('/bar'))->getData());
        $this->assertEquals($route2->getData(), $serializedMatcher->match('GET', new Uri('/bar@'))->getData());
        $this->assertEquals($route3->getData(), $serializedMatcher->match('GET', new Uri('/foo/bar'))->getData());
        $this->assertEquals($route3->getData(), $serializedMatcher->match('GET', new Uri('/foo/bar/'))->getData());
        $this->assertEquals($route4->getData(), $serializedMatcher->match('GET', new Uri('/bar/foo'))->getData());
        $this->assertEquals($route4->getData(), $serializedMatcher->match('GET', new Uri('/bar/foo@'))->getData());
    }

    public function testMatchingEndingSlashConflict(): void
    {
        $collection = new RouteCollection();
        $route1 = $collection->addRoute('/foo', Route::DEFAULT_METHODS)->getRoute();
        $route2 = $collection->addRoute('/foo/', Route::DEFAULT_METHODS)->getRoute();
        $route3 = $collection->addRoute('/foo/', ['POST'])->getRoute();
        $route4 = $collection->addRoute('/bar/{var}', Route::DEFAULT_METHODS)->getRoute();
        $route5 = $collection->addRoute('/bar/{var}/', Route::DEFAULT_METHODS)->getRoute();

        $matcher = new RouteMatcher($collection);
        $this->assertSame($route1, $matcher->match('GET', new Uri('/foo')));
        $this->assertSame($route2, $matcher->match('GET', new Uri('/foo/')));
        $this->assertSame($route3, $matcher->match('POST', new Uri('/foo/')));
        $this->assertSame($route4, $matcher->match('GET', new Uri('/bar/foo')));
        $this->assertSame($route5, $matcher->match('GET', new Uri('/bar/foo/')));

        $serializedMatcher = \unserialize(\serialize($matcher));
        $this->assertInstanceOf(RouteMatcherInterface::class, $serializedMatcher);

        $this->assertEquals($route1->getData(), $serializedMatcher->match('GET', new Uri('/foo'))->getData());
        $this->assertEquals($route2->getData(), $serializedMatcher->match('GET', new Uri('/foo/'))->getData());
        $this->assertEquals($route3->getData(), $serializedMatcher->match('POST', new Uri('/foo/'))->getData());
        $this->assertEquals($route4->getData(), $serializedMatcher->match('GET', new Uri('/bar/foo'))->getData());
        $this->assertNotEquals($route5->getData(), $serializedMatcher->match('GET', new Uri('/bar/foo/')));
    }

    public function testRouteMatchingError(): void
    {
        $collection = new RouteCollection();
        $route1 = $collection->addRoute('/foo', Route::DEFAULT_METHODS)->getRoute();
        $route2 = $collection->addRoute('/bar/{var:[a-z]+}', Route::DEFAULT_METHODS)->getRoute();

        $matcher = new RouteMatcher($collection);
        $this->assertSame($route1, $matcher->match('GET', new Uri('/foo')));
        $this->assertNull($matcher->match('GET', new Uri('/foo/')));
        $this->assertSame($route2, $matcher->match('GET', new Uri('/bar/foo')));
        $this->assertNull($matcher->match('GET', new Uri('/bar/foo/')));

        $serializedMatcher = \unserialize(\serialize($matcher));
        $this->assertInstanceOf(RouteMatcherInterface::class, $serializedMatcher);

        $this->assertEquals($route1->getData(), $serializedMatcher->match('GET', new Uri('/foo'))->getData());
        $this->assertNull($serializedMatcher->match('GET', new Uri('/foo/')));
        $this->assertEquals($route2->getData(), $serializedMatcher->match('GET', new Uri('/bar/foo'))->getData());
        $this->assertNull($serializedMatcher->match('GET', new Uri('/bar/foo/')));
    }

    public function testDuplicationOnDynamicRoutePattern(): void
    {
        $collection = new RouteCollection();
        $route1 = $collection->addRoute('[{locale:en|fr}]/admin/post/{id:int}/', Route::DEFAULT_METHODS)->getRoute();
        $collection->addRoute('[{locale:en|fr}]/admin/post/{id:int}/', Route::DEFAULT_METHODS)->getRoute();

        $matcher = new RouteMatcher($collection);
        $this->assertEquals($route1, $matcher->match('GET', new Uri('/admin/post/23')));

        $serializedMatcher = \unserialize(\serialize($matcher));
        $this->assertInstanceOf(RouteMatcherInterface::class, $serializedMatcher);
        $this->assertEquals($route1->argument('locale', 'en'), $serializedMatcher->match('GET', new Uri('en/admin/post/23')));
    }

    /**
     * @dataProvider provideCompileData
     *
     * @param array<string,string> $tokens
     */
    public function testGenerateUri(string $regex, string $match, array $tokens, int $referenceType): void
    {
        $collection = new RouteCollection();
        $collection->addRoute($regex, ['FOO', 'BAR'])->bind('test');

        $factory = new RouteMatcher($collection);

        $this->assertEquals($match, (string) $factory->generateUri('test', $tokens, $referenceType));
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

        $this->assertEquals('/fifty', $factory->generateUri('test', [], GeneratedUri::NETWORK_PATH));
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
            GeneratedUri::RELATIVE_PATH,
        ];

        yield 'Build route with variable and domain' => [
            'http://[{lang:[a-z]{2}}.]example.com/{foo}',
            'http://example.com/cool',
            ['foo' => 'cool'],
            GeneratedUri::RELATIVE_PATH,
        ];

        yield 'Build route with variable and default' => [
            '/{foo=cool}',
            './cool',
            [],
            GeneratedUri::RELATIVE_PATH,
        ];

        yield 'Build route with variable and override default' => [
            '/{foo=cool}',
            './yeah',
            ['foo' => 'yeah'],
            GeneratedUri::RELATIVE_PATH,
        ];

        yield 'Build route with absolute path reference type' => [
            '/world',
            '/world',
            [],
            GeneratedUri::ABSOLUTE_PATH,
        ];

        yield 'Build route with domain and network reference type' => [
            '//hello.com/world',
            '//hello.com/world',
            [],
            GeneratedUri::NETWORK_PATH,
        ];

        yield 'Build route with domain, port 8080 and absolute url reference type' => [
            '//hello.com:8080/world',
            '//hello.com:8080/world',
            [],
            GeneratedUri::ABSOLUTE_URL,
        ];

        yield 'Build route with domain, port 88 and absolute path reference type' => [
            '//hello.com:88/world',
            '//hello.com:88/world',
            [],
            GeneratedUri::ABSOLUTE_URL,
        ];
    }
}
