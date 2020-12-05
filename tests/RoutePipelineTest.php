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

use DivineNii\Invoker\Invoker;
use Flight\Routing\Exceptions\DuplicateRouteException;
use Flight\Routing\Exceptions\InvalidMiddlewareException;
use Flight\Routing\Exceptions\RouteNotFoundException;
use Flight\Routing\RouteCollector;
use GuzzleHttp\Psr7\ServerRequest;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * RoutePipelineTest
 */
class RoutePipelineTest extends BaseTestCase
{
    public function testAddMiddleware(): void
    {
        $middlewares = [
            new Fixtures\BlankMiddleware(),
            new Fixtures\BlankMiddleware(),
            Fixtures\BlankMiddleware::class,
        ];

        $pipeline = $this->getRouter();
        $pipeline->addMiddleware(...$middlewares);

        $pipeline->addMiddleware(['hello' => new Fixtures\NamedBlankMiddleware('test')]);

        $this->assertSame($middlewares, $pipeline->getMiddlewares());
        $this->assertNotContains('hello', $middlewares);
    }

    public function testEmptyRequestHandler(): void
    {
        $middlewares = [
            new Fixtures\BlankMiddleware(),
            new Fixtures\BlankMiddleware(),
            Fixtures\BlankMiddleware::class,
        ];

        $pipeline = $this->getRouter();
        $pipeline->addMiddleware(...$middlewares);

        $this->expectExceptionMessage('Unable to find the controller for path "test". The route is wrongly configured.');
        $this->expectException(RouteNotFoundException::class);

        $pipeline->handle(new ServerRequest('GET', 'test'));
    }

    public function testAddExistingMiddleware(): void
    {
        $middleware = new Fixtures\BlankMiddleware();

        $pipeline = $this->getRouter();
        $pipeline->addMiddleware($middleware);

        $this->expectException(DuplicateRouteException::class);

        $pipeline->addMiddleware($middleware);
    }

    public function testHandleWithMiddlewares(): void
    {
        $route = new Fixtures\TestRoute();

        $middlewares = [
            new Fixtures\BlankMiddleware(),
            new Fixtures\BlankMiddleware(),
            Fixtures\BlankMiddleware::class,
            [new Fixtures\BlankMiddleware(), 'process'],
        ];

        ($pipeline = $this->getRouter())->addRoute($route);
        $pipeline->addMiddleware(...$middlewares);

        $response = $pipeline->handle(new ServerRequest(
            $route->getMethods()[0],
            $route->getPath(),
            [],
            null,
            '1.1',
            ['Broken' => 'test']
        ));

        $this->assertTrue($middlewares[0]->isRunned());
        $this->assertTrue($middlewares[1]->isRunned());
        $this->assertTrue($response->hasHeader('Middleware'));
        $this->assertEquals('broken', $response->getHeaderLine('Middleware-Broken'));
    }

    public function testHandleMiddlewareWithContainer(): void
    {
        $route = new Fixtures\TestRoute();

        $container = $this->getMockBuilder(ContainerInterface::class)->getMock();
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn(new Fixtures\BlankMiddleware());

        ($pipeline = $this->getRouter(null, new Invoker([], $container)))->addRoute($route);
        $pipeline->addMiddleware('container');

        $response = $pipeline->handle(new ServerRequest($route->getMethods()[0], $route->getPath()));

        $this->assertTrue($response->hasHeader('Middleware'));
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testHandleWithBrokenMiddleware(): void
    {
        $route = new Fixtures\TestRoute();

        $middlewares = [
            new Fixtures\BlankMiddleware(),
            new Fixtures\BlankMiddleware(true),
            new Fixtures\BlankMiddleware(),
        ];

        ($pipeline = $this->getRouter())->addRoute($route);
        $pipeline->addMiddleware(...$middlewares);

        $pipeline->handle(new ServerRequest($route->getMethods()[0], $route->getPath()));

        $this->assertTrue($middlewares[0]->isRunned());
        $this->assertTrue($middlewares[1]->isRunned());
        $this->assertFalse($middlewares[2]->isRunned());
    }

    public function testHandleInvalidMiddleware(): void
    {
        $route = new Fixtures\TestRoute();

        ($pipeline = $this->getRouter())->addRoute($route);
        $pipeline->addMiddleware('none');

        $this->expectExceptionMessage(
            'Middleware "none" is neither a string service name, a PHP callable, ' .
            'a Psr\Http\Server\MiddlewareInterface instance, a Psr\Http\Server\RequestHandlerInterface instance, ' .
            'or an array of such arguments'
        );
        $this->expectException(InvalidMiddlewareException::class);

        $pipeline->handle(new ServerRequest($route->getMethods()[0], $route->getPath()));
    }

    /**
     * @dataProvider hasCollectionGroupData
     *
     * @param string $expectedMethod
     * @param string $expectedUri
     */
    public function testHandleCollectionGrouping(string $expectedMethod, string $expectedUri): void
    {
        $collector = new RouteCollector();

        $collector->group(function (RouteCollector $group): void {
            $group->get('home', '/', new Fixtures\BlankRequestHandler());
            $group->get('ping', '/ping', new Fixtures\BlankRequestHandler());

            $group->group(function (RouteCollector $group): void {
                $group->head('greeting', 'hello/{me}', new Fixtures\BlankRequestHandler());
            })
            ->addPrefix('/v1')
            ->addMiddleware('hello')
            ->addDomain('https://biurad.com');
        })->addPrefix('/api')->setName('api.');

        ($pipeline = $this->getRouter())->addRoute(...$collector->getCollection());
        $pipeline->addMiddleware(['hello' => Fixtures\BlankMiddleware::class]);

        $response = $pipeline->handle(new ServerRequest($expectedMethod, $expectedUri));

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    /**
     * @return string[]
     */
    public function hasCollectionGroupData(): array
    {
        return [
            [RouteCollector::METHOD_GET, '/api'],
            [RouteCollector::METHOD_GET, '/api/ping'],
            [RouteCollector::METHOD_HEAD, 'https://biurad.com/api/v1/hello/23'],
        ];
    }
}
