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

use BiuradPHP\Http\Factory\ServerRequestFactory;
use BiuradPHP\Http\Response;
use BiuradPHP\Http\Uri;
use Flight\Routing\Middlewares\UriRedirectMiddleware;
use Flight\Routing\Route;
use Flight\Routing\Router;
use Flight\Routing\Tests\Fixtures\BlankRequestHandler;
use Generator;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * UriRedirectMiddlewareTest
 */
class UriRedirectMiddlewareTest extends TestCase
{
    public function getRouter(): Router
    {
        $responseFactory = $this->getMockBuilder(ResponseFactoryInterface::class)->getMock();
        $responseFactory->method('createResponse')->willReturn(new Response());

        return new Router($responseFactory);
    }

    /**
     * @dataProvider redirectionData
     *
     * @param array<string,string|UriInterface> $redirects
     * @param string                            $expected
     */
    public function testProcess(array $redirects, string $expected): void
    {
        $router = $this->getRouter();
        $router->addMiddleware(new UriRedirectMiddleware($redirects));

        $routes = [
            new Route('uri_middleware_expected', ['GET'], $expected, BlankRequestHandler::class),
        ];
        $router->addRoute(...$routes);

        $request  = (new ServerRequestFactory())->createServerRequest('GET', $expected);
        $response = $router->handle($request);

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
        $router = $this->getRouter();
        $router->addMiddleware(new UriRedirectMiddleware($redirects));

        $routes = [
            new Route('uri_middleware_expected', ['GET'], $expected, BlankRequestHandler::class),
            new Route('uri_middleware', ['GET'], \key($redirects), BlankRequestHandler::class),
        ];
        $router->addRoute(...$routes);

        $request  = (new ServerRequestFactory())->createServerRequest('GET', \key($redirects));
        $response = $router->handle($request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(301, $response->getStatusCode());
    }

    public function testProcessWithQuery(): void
    {
        $router     = $this->getRouter();
        $middleware = new UriRedirectMiddleware(['page?hello=me' => 'account/me']);
        $router->addMiddleware($middleware->allowQueries(true));

        $route = new Route('uri_middleware_expected', ['GET'], 'page', BlankRequestHandler::class);
        $router->addRoute($route);

        $uri      = (new Uri('page'))->withQuery('hello=me');
        $request  = (new ServerRequestFactory())->createServerRequest('GET', $uri);
        $response = $router->handle($request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(301, $response->getStatusCode());
    }

    public function testProcessWithPermanent(): void
    {
        $router     = $this->getRouter();
        $middleware = new UriRedirectMiddleware(['foo' => 'bar']);
        $router->addMiddleware($middleware->setPermanentRedirection(false));

        $route = new Route('uri_middleware_expected', ['POST'], 'foo', BlankRequestHandler::class);
        $router->addRoute($route);

        $request  = (new ServerRequestFactory())->createServerRequest('POST', 'foo');
        $response = $router->handle($request);

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
