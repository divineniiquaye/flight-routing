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

namespace Flight\Routing\Tests\Middlewares;

use Flight\Routing\Middlewares\UriRedirectMiddleware;
use Flight\Routing\Routes\Route;
use Flight\Routing\Router;
use Flight\Routing\Tests\BaseTestCase;
use Flight\Routing\Tests\Fixtures\BlankRequestHandler;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Uri;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * UriRedirectMiddlewareTest.
 */
class UriRedirectMiddlewareTest extends BaseTestCase
{
    /**
     * @dataProvider redirectionData
     *
     * @param array<string,string|UriInterface> $redirects
     */
    public function testProcess(array $redirects, string $expected): void
    {
        $pipeline = Router::withCollection();
        $pipeline->pipe(new UriRedirectMiddleware($redirects));

        $route = new Route($expected, Router::METHOD_GET, BlankRequestHandler::class);
        $pipeline->addRoute($route);

        $response = $pipeline->process(new ServerRequest(Router::METHOD_GET, $expected), $this->getRequestHandler());

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * @dataProvider redirectionData
     *
     * @param array<string,string|UriInterface> $redirects
     */
    public function testProcessWithRedirect(array $redirects, string $expected): void
    {
        $pipeline = Router::withCollection();
        $pipeline->pipe(new UriRedirectMiddleware($redirects));

        $route = new Route($expected, Router::METHOD_GET, BlankRequestHandler::class);
        $pipeline->addRoute($route);

        $response = $pipeline->process(new ServerRequest(Router::METHOD_GET, $actualPath = \key($redirects)), $this->getRequestHandler());

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(301, $response->getStatusCode());
        $this->assertEquals($redirects[$actualPath], $response->getHeaderLine('Location'));
    }

    /**
     * @dataProvider redirectionData
     *
     * @param array<string,string|UriInterface> $redirects
     */
    public function testProcessWithRedirectAndKeepMethod(array $redirects, string $expected): void
    {
        $pipeline = Router::withCollection();
        $pipeline->pipe(new UriRedirectMiddleware($redirects, true));

        $route = new Route($expected, Router::METHOD_POST, BlankRequestHandler::class);
        $pipeline->addRoute($route);

        $response = $pipeline->process(new ServerRequest(Router::METHOD_POST, $actualPath = \key($redirects)), $this->getRequestHandler());

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(308, $response->getStatusCode());
        $this->assertEquals($redirects[$actualPath], $response->getHeaderLine('Location'));
    }

    public function testProcessWithAllAttributes(): void
    {
        $pipeline = Router::withCollection();
        $pipeline->pipe(new UriRedirectMiddleware(['/user/\d+' => '#/account/me']));

        $route = new Route('/account/me', Router::METHOD_GET, BlankRequestHandler::class);
        $pipeline->addRoute($route);

        $uri = new Uri('/user/23?page=settings#notification');
        $response = $pipeline->process(new ServerRequest(Router::METHOD_GET, $uri), $this->getRequestHandler());

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(301, $response->getStatusCode());
        $this->assertEquals('/account/me?page=settings#notification', $response->getHeaderLine('Location'));
    }

    public function redirectionData(): \Generator
    {
        yield 'Redirect string with symbols' => [
            ['/@come_here' => '/ch'], '/ch',
        ];

        yield 'Redirect string with format' => [
            ['/index.html' => '/home'], '/home',
        ];

        yield 'Redirect string with format reverse' => [
            ['/home' => '/index.html'], '/index.html',
        ];

        yield 'Redirect string with Uri instance' => [
            ['/sdjfdkgjdg' => new Uri('./cool')], '/cool',
        ];
    }
}
