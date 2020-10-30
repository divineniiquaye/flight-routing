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

namespace Flight\Routing\Tests\Middlewares;

use Flight\Routing\Middlewares\UriRedirectMiddleware;
use Flight\Routing\Route;
use Flight\Routing\RoutePipeline;
use Flight\Routing\Tests\BaseTestCase;
use Flight\Routing\Tests\Fixtures\BlankRequestHandler;
use Generator;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * UriRedirectMiddlewareTest
 */
class UriRedirectMiddlewareTest extends BaseTestCase
{
    /**
     * @dataProvider redirectionData
     *
     * @param array<string,string|UriInterface> $redirects
     * @param string                            $expected
     */
    public function testProcess(array $redirects, string $expected): void
    {
        $router   = $this->getRouter();
        $pipeline = new RoutePipeline();
        $pipeline->addMiddleware(new UriRedirectMiddleware($redirects));

        $routes = [
            new Route('uri_middleware_expected', ['GET'], $expected, BlankRequestHandler::class),
        ];
        $router->addRoute(...$routes);

        $response = $pipeline->process(new ServerRequest('GET', $expected), $router);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * @dataProvider redirectionData
     *
     * @param array<string,string|UriInterface> $redirects
     * @param string                            $expected
     */
    public function testProcessWithRedirect(array $redirects, string $expected): void
    {
        $router   = $this->getRouter();
        $pipeline = new RoutePipeline();
        $pipeline->addMiddleware(new UriRedirectMiddleware($redirects));

        $routes = [
            new Route('uri_middleware_expected', ['GET'], $expected, BlankRequestHandler::class),
            new Route('uri_middleware', ['GET'], \key($redirects), BlankRequestHandler::class),
        ];
        $router->addRoute(...$routes);

        $response = $pipeline->process(new ServerRequest('GET', \key($redirects)), $router);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(301, $response->getStatusCode());
    }

    public function testProcessWithQuery(): void
    {
        $router     = $this->getRouter();
        $pipeline   = new RoutePipeline();
        $middleware = new UriRedirectMiddleware(['page?hello=me' => 'account/me']);
        $pipeline->addMiddleware($middleware->allowQueries(true));

        $route = new Route('uri_middleware_expected', ['GET'], 'page', BlankRequestHandler::class);
        $router->addRoute($route);

        $uri      = (new Uri('page'))->withQuery('hello=me');
        $response = $pipeline->process(new ServerRequest('GET', $uri), $router);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(301, $response->getStatusCode());
    }

    public function testProcessWithPermanent(): void
    {
        $router     = $this->getRouter();
        $pipeline   = new RoutePipeline();
        $middleware = new UriRedirectMiddleware(['foo' => 'bar']);
        $pipeline->addMiddleware($middleware->setPermanentRedirection(false));

        $route = new Route('uri_middleware_expected', ['POST'], 'foo', BlankRequestHandler::class);
        $router->addRoute($route);

        $response = $pipeline->process(new ServerRequest('POST', 'foo'), $router);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(307, $response->getStatusCode());
    }

    /**
     * @return Generator
     */
    public function redirectionData(): Generator
    {
        yield 'Redirect string with symbols' => [
            ['@come_here' => 'ch'], 'ch',
        ];

        yield 'Redirect string with format' => [
            ['index.html' => 'home'], 'home',
        ];

        yield 'Redirect string with format reverse' => [
            ['home' => 'index.html'], 'index.html',
        ];

        yield 'Redirect string with Uri instance' => [
            ['sdjfdkgjdg' => new Uri('./cool')], 'cool',
        ];
    }
}
