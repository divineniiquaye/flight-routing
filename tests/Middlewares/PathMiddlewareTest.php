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

use Flight\Routing\Middlewares\PathMiddleware;
use Flight\Routing\Routes\Route;
use Flight\Routing\Router;
use Flight\Routing\Tests\BaseTestCase;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PathMiddlewareTest.
 */
class PathMiddlewareTest extends BaseTestCase
{
    public function testProcessStatus(): void
    {
        $pipeline = Router::withCollection();
        $pipeline->pipe(new PathMiddleware());
        $pipeline->addRoute(new Route('/foo', Router::METHOD_GET, [$this, 'handlePath']));

        $response = $pipeline->process(new ServerRequest(Router::METHOD_GET, '/foo'), $this->getRequestHandler());

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertFalse($response->hasHeader('Location'));
    }

    /**
     * @dataProvider pathCombinationsData
     */
    public function testProcessWithPermanent(string $uriPath, string $requestPath, string $expectedPath, int $expectsStatus): void
    {
        $pipeline = Router::withCollection();
        $pipeline->pipe(new PathMiddleware(true));
        $pipeline->addRoute(new Route($uriPath, [Router::METHOD_GET, Router::METHOD_POST], [$this, 'handlePath']));

        $response = $pipeline->process(new ServerRequest(Router::METHOD_GET, $requestPath), $this->getRequestHandler());

        $this->assertEquals($expectsStatus, $response->getStatusCode());
        $this->assertEquals($expectedPath, $response->getHeaderLine('Location'));
    }

    /**
     * @dataProvider pathCombinationsData
     */
    public function testProcessWithoutPermanent(string $uriPath, string $requestPath, string $expectedPath, int $expectsStatus): void
    {
        $pipeline = Router::withCollection();
        $pipeline->pipe(new PathMiddleware());
        $pipeline->addRoute(new Route($uriPath, [Router::METHOD_GET, Router::METHOD_POST], [$this, 'handlePath']));

        $response = $pipeline->process(new ServerRequest(Router::METHOD_GET, $requestPath), $this->getRequestHandler());

        $this->assertEquals(301 === $expectsStatus ? 302 : $expectsStatus, $response->getStatusCode());
        $this->assertEquals($expectedPath, $response->getHeaderLine('Location'));
    }

    /**
     * @dataProvider pathCombinationsData
     */
    public function testProcessWithPermanentAndKeepMethod(string $uriPath, string $requestPath, string $expectedPath, int $expectsStatus): void
    {
        $pipeline = Router::withCollection();
        $pipeline->pipe(new PathMiddleware(true, true));
        $pipeline->addRoute(new Route($uriPath, [Router::METHOD_GET, Router::METHOD_POST], [$this, 'handlePath']));

        $response = $pipeline->process(new ServerRequest(Router::METHOD_POST, $requestPath), $this->getRequestHandler());
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(301 === $expectsStatus ? 308 : $expectsStatus, $response->getStatusCode());
        $this->assertEquals($expectedPath, $response->getHeaderLine('Location'));
    }

    /**
     * @dataProvider pathCombinationsData
     */
    public function testProcessWithoutPermanentAndKeepMethod(string $uriPath, string $requestPath, string $expectedPath, int $expectsStatus): void
    {
        $pipeline = Router::withCollection();
        $pipeline->pipe(new PathMiddleware(false, false));
        $pipeline->addRoute(new Route($uriPath, [Router::METHOD_GET, Router::METHOD_POST], [$this, 'handlePath']));

        $response = $pipeline->process(new ServerRequest(Router::METHOD_POST, $requestPath), $this->getRequestHandler());
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(301 === $expectsStatus ? 302 : $expectsStatus, $response->getStatusCode());
        $this->assertEquals($expectedPath, $response->getHeaderLine('Location'));
    }

    /**
     * @dataProvider pathCombinationsData
     */
    public function testProcessWithoutPermenantButKeepMethod(string $uriPath, string $requestPath, string $expectedPath, int $expectsStatus): void
    {
        $pipeline = Router::withCollection();
        $pipeline->pipe(new PathMiddleware(false, true));
        $pipeline->addRoute(new Route($uriPath, [Router::METHOD_GET, Router::METHOD_POST], [$this, 'handlePath']));

        $response = $pipeline->process(new ServerRequest(Router::METHOD_GET, $requestPath), $this->getRequestHandler());
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(301 === $expectsStatus ? 307 : $expectsStatus, $response->getStatusCode());
        $this->assertEquals($expectedPath, $response->getHeaderLine('Location'));
    }

    public function handlePath(ResponseFactoryInterface $responseFactory): ResponseInterface
    {
        return $responseFactory->createResponse();
    }

    /**
     * @return array<int,array<string
     */
    public function pathCombinationsData(): array
    {
        return [
            // name => [$uriPath, $requestPath, $expectedPath, $permanent ]
            'root-without-prefix-tail_1' => ['/foo', '/foo', '', 200],
            'root-without-prefix-tail_2' => ['/foo', '/foo', '', 200],
            'root-without-prefix-tail_3' => ['/foo', '/foo/', '/foo', 301],
            'root-without-prefix-tail_4' => ['/foo', '/foo/', '/foo', 301],
            'root-with-prefix-tail_1' => ['/foo/', '/foo/', '', 200],
            'root-with-prefix-tail_2' => ['/foo/', '/foo/', '', 200],
            'root-with-prefix-tail_3' => ['/foo/', '/foo', '/foo/', 301],
            'root-with-prefix-tail_4' => ['/foo/', '/foo', '/foo/', 301],
        ];
    }
}
