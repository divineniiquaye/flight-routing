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

use Flight\Routing\Exceptions\InvalidControllerException;
use Flight\Routing\Interfaces\RouteCollectionInterface;
use Flight\Routing\Interfaces\RouteFactoryInterface;
use Flight\Routing\Interfaces\RouteInterface;
use Flight\Routing\RouteCollection;
use Flight\Routing\RouteCollector;
use PHPUnit\Framework\TestCase;

/**
 * RouteCollectorTest
 */
class RouteCollectorTest extends TestCase
{
    public function testDefaultCollection(): void
    {
        $collector = new RouteCollector();

        $this->assertInstanceOf(RouteCollectionInterface::class, $collector->getCollection());
    }

    public function testRouteFactory(): void
    {
        $expectedRoute      = new Fixtures\TestRoute();
        $expectedCollection = new RouteCollection();

        $routeFactory = $this->createMock(RouteFactoryInterface::class);
        $routeFactory->method('createRoute')->willReturn($expectedRoute);
        $routeFactory->method('createCollection')->willReturn($expectedCollection);

        $collector  = new RouteCollector($routeFactory);
        $builtRoute = $collector->map(
            'test',
            [$collector::METHOD_GET],
            '/test',
            new Fixtures\BlankRequestHandler()
        );

        $this->assertSame($expectedRoute, $builtRoute);
        $this->assertSame($expectedCollection, $collector->getCollection());
        $this->assertNotEmpty($collector->__debugInfo());
    }

    public function testRouteFactoryThroughHeadMethod(): void
    {
        $expectedRoute = new Fixtures\TestRoute();

        $routeFactory = $this->createMock(RouteFactoryInterface::class);
        $routeFactory->method('createRoute')->willReturn($expectedRoute);

        $collector  = new RouteCollector($routeFactory);
        $builtRoute = $collector->head('test', '/test', new Fixtures\BlankRequestHandler());

        $this->assertSame($expectedRoute, $builtRoute);
    }

    public function testRouteFactoryThroughGetMethod(): void
    {
        $expectedRoute = new Fixtures\TestRoute();

        $routeFactory = $this->createMock(RouteFactoryInterface::class);
        $routeFactory->method('createRoute')->willReturn($expectedRoute);

        $collector  = new RouteCollector($routeFactory);
        $builtRoute = $collector->get('test', '/test', new Fixtures\BlankRequestHandler());

        $this->assertSame($expectedRoute, $builtRoute);
    }

    public function testRouteFactoryThroughPostMethod(): void
    {
        $expectedRoute = new Fixtures\TestRoute();

        $routeFactory = $this->createMock(RouteFactoryInterface::class);
        $routeFactory->method('createRoute')->willReturn($expectedRoute);

        $collector  = new RouteCollector($routeFactory);
        $builtRoute = $collector->post('test', '/test', new Fixtures\BlankRequestHandler());

        $this->assertSame($expectedRoute, $builtRoute);
    }

    public function testRouteFactoryThroughPutMethod(): void
    {
        $expectedRoute = new Fixtures\TestRoute();

        $routeFactory = $this->createMock(RouteFactoryInterface::class);
        $routeFactory->method('createRoute')->willReturn($expectedRoute);

        $collector  = new RouteCollector($routeFactory);
        $builtRoute = $collector->put('test', '/test', new Fixtures\BlankRequestHandler());

        $this->assertSame($expectedRoute, $builtRoute);
    }

    public function testRouteFactoryThroughPatchMethod(): void
    {
        $expectedRoute = new Fixtures\TestRoute();

        $routeFactory = $this->createMock(RouteFactoryInterface::class);
        $routeFactory->method('createRoute')->willReturn($expectedRoute);

        $collector  = new RouteCollector($routeFactory);
        $builtRoute = $collector->patch('test', '/test', new Fixtures\BlankRequestHandler());

        $this->assertSame($expectedRoute, $builtRoute);
    }

    public function testRouteFactoryThroughDeleteMethod(): void
    {
        $expectedRoute = new Fixtures\TestRoute();

        $routeFactory = $this->createMock(RouteFactoryInterface::class);
        $routeFactory->method('createRoute')->willReturn($expectedRoute);

        $collector  = new RouteCollector($routeFactory);
        $builtRoute = $collector->delete('test', '/test', new Fixtures\BlankRequestHandler());

        $this->assertSame($expectedRoute, $builtRoute);
    }

    public function testFactoriesTransferringDuringGrouping(): void
    {
        $routeFactory = $this->createMock(RouteFactoryInterface::class);

        $routeFactory->expects($this->exactly(4))
            ->method('createCollection')
            ->willReturn(new RouteCollection());

        $routeFactory->expects($this->exactly(1))
            ->method('createRoute')
            ->willReturn(new Fixtures\TestRoute());

        $collector = new RouteCollector($routeFactory);

        $collector->group(function ($collector): void {
            $collector->group(function ($collector): void {
                $collector->group(function ($collector): void {
                    $collector->map('test', ['GET'], '/test', new Fixtures\BlankRequestHandler());
                });
            });
        });
    }

    public function testRoute(): void
    {
        $routeName           = Fixtures\TestRoute::getTestRouteName();
        $routePath           = Fixtures\TestRoute::getTestRoutePath();
        $routeMethods        = Fixtures\TestRoute::getTestRouteMethods();
        $routeRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();

        $collector = new RouteCollector();

        $route = $collector->map(
            $routeName,
            $routeMethods,
            $routePath,
            $routeRequestHandler
        );

        $this->assertInstanceOf(RouteInterface::class, $route);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame($routeMethods, $route->getMethods());
        $this->assertSame($routeRequestHandler, $route->getController());

        // default property values...
        $this->assertSame([], $route->getMiddlewares());
        $this->assertSame([], $route->getArguments());
        $this->assertSame([], $route->getDefaults());
        $this->assertSame([], $route->getSchemes());
        $this->assertSame([], $route->getPatterns());
    }

    public function testRouteWithOptionalParams(): void
    {
        $routeName           = Fixtures\TestRoute::getTestRouteName();
        $routePath           = Fixtures\TestRoute::getTestRoutePath();
        $routeMethods        = Fixtures\TestRoute::getTestRouteMethods();
        $routeRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();
        $routeMiddlewares    = Fixtures\TestRoute::getTestRouteMiddlewares();
        $routeAttributes     = Fixtures\TestRoute::getTestRouteAttributes();

        $collector = new RouteCollector();

        $route = $collector->map(
            $routeName,
            $routeMethods,
            $routePath,
            $routeRequestHandler
        )
        ->addMiddleware(...$routeMiddlewares)
        ->setArguments($routeAttributes);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame($routeMethods, $route->getMethods());
        $this->assertSame($routeRequestHandler, $route->getController());
        $this->assertSame($routeMiddlewares, $route->getMiddlewares());
        $this->assertSame($routeAttributes, $route->getArguments());
    }

    public function testHead(): void
    {
        $routeName           = Fixtures\TestRoute::getTestRouteName();
        $routePath           = Fixtures\TestRoute::getTestRoutePath();
        $routeRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();

        $collector = new RouteCollector();

        $route = $collector->head(
            $routeName,
            $routePath,
            $routeRequestHandler
        );

        $this->assertInstanceOf(RouteInterface::class, $route);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame([$collector::METHOD_HEAD], $route->getMethods());
        $this->assertSame($routeRequestHandler, $route->getController());

        // default property values...
        $this->assertSame([], $route->getMiddlewares());
        $this->assertSame([], $route->getArguments());
        $this->assertSame([], $route->getDefaults());
        $this->assertSame([], $route->getSchemes());
        $this->assertSame([], $route->getPatterns());
    }

    public function testHeadWithOptionalParams(): void
    {
        $routeName           = Fixtures\TestRoute::getTestRouteName();
        $routePath           = Fixtures\TestRoute::getTestRoutePath();
        $routeRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();
        $routeMiddlewares    = Fixtures\TestRoute::getTestRouteMiddlewares();
        $routeAttributes     = Fixtures\TestRoute::getTestRouteAttributes();

        $collector = new RouteCollector();

        $route = $collector->head(
            $routeName,
            $routePath,
            $routeRequestHandler
        )
        ->addMiddleware(...$routeMiddlewares)
        ->setArguments($routeAttributes);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame([$collector::METHOD_HEAD], $route->getMethods());
        $this->assertSame($routeRequestHandler, $route->getController());
        $this->assertSame($routeMiddlewares, $route->getMiddlewares());
        $this->assertSame($routeAttributes, $route->getArguments());
    }

    public function testGet(): void
    {
        $routeName           = Fixtures\TestRoute::getTestRouteName();
        $routePath           = Fixtures\TestRoute::getTestRoutePath();
        $routeRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();

        $collector = new RouteCollector();

        $route = $collector->get(
            $routeName,
            $routePath,
            $routeRequestHandler
        );

        $this->assertInstanceOf(RouteInterface::class, $route);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame([$collector::METHOD_GET, $collector::METHOD_HEAD], $route->getMethods());
        $this->assertSame($routeRequestHandler, $route->getController());

        // default property values...
        $this->assertSame([], $route->getMiddlewares());
        $this->assertSame([], $route->getArguments());
        $this->assertSame([], $route->getDefaults());
        $this->assertSame([], $route->getSchemes());
        $this->assertSame([], $route->getPatterns());
    }

    public function testGetWithOptionalParams(): void
    {
        $routeName           = Fixtures\TestRoute::getTestRouteName();
        $routePath           = Fixtures\TestRoute::getTestRoutePath();
        $routeRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();
        $routeMiddlewares    = Fixtures\TestRoute::getTestRouteMiddlewares();
        $routeAttributes     = Fixtures\TestRoute::getTestRouteAttributes();

        $collector = new RouteCollector();

        $route = $collector->get(
            $routeName,
            $routePath,
            $routeRequestHandler
        )
        ->addMiddleware(...$routeMiddlewares)
        ->setArguments($routeAttributes);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame([$collector::METHOD_GET, $collector::METHOD_HEAD], $route->getMethods());
        $this->assertSame($routeRequestHandler, $route->getController());
        $this->assertSame($routeMiddlewares, $route->getMiddlewares());
        $this->assertSame($routeAttributes, $route->getArguments());
    }

    public function testPost(): void
    {
        $routeName           = Fixtures\TestRoute::getTestRouteName();
        $routePath           = Fixtures\TestRoute::getTestRoutePath();
        $routeRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();

        $collector = new RouteCollector();

        $route = $collector->post(
            $routeName,
            $routePath,
            $routeRequestHandler
        );

        $this->assertInstanceOf(RouteInterface::class, $route);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame([$collector::METHOD_POST], $route->getMethods());
        $this->assertSame($routeRequestHandler, $route->getController());

        // default property values...
        $this->assertSame([], $route->getMiddlewares());
        $this->assertSame([], $route->getArguments());
        $this->assertSame([], $route->getDefaults());
        $this->assertSame([], $route->getSchemes());
        $this->assertSame([], $route->getPatterns());
    }

    public function testPostWithOptionalParams(): void
    {
        $routeName           = Fixtures\TestRoute::getTestRouteName();
        $routePath           = Fixtures\TestRoute::getTestRoutePath();
        $routeRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();
        $routeMiddlewares    = Fixtures\TestRoute::getTestRouteMiddlewares();
        $routeAttributes     = Fixtures\TestRoute::getTestRouteAttributes();

        $collector = new RouteCollector();

        $route = $collector->post(
            $routeName,
            $routePath,
            $routeRequestHandler
        )
        ->addMiddleware(...$routeMiddlewares)
        ->setArguments($routeAttributes);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame([$collector::METHOD_POST], $route->getMethods());
        $this->assertSame($routeRequestHandler, $route->getController());
        $this->assertSame($routeMiddlewares, $route->getMiddlewares());
        $this->assertSame($routeAttributes, $route->getArguments());
    }

    public function testPut(): void
    {
        $routeName           = Fixtures\TestRoute::getTestRouteName();
        $routePath           = Fixtures\TestRoute::getTestRoutePath();
        $routeRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();

        $collector = new RouteCollector();

        $route = $collector->put(
            $routeName,
            $routePath,
            $routeRequestHandler
        );

        $this->assertInstanceOf(RouteInterface::class, $route);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame([$collector::METHOD_PUT], $route->getMethods());
        $this->assertSame($routeRequestHandler, $route->getController());

        // default property values...
        $this->assertSame([], $route->getMiddlewares());
        $this->assertSame([], $route->getArguments());
        $this->assertSame([], $route->getDefaults());
        $this->assertSame([], $route->getSchemes());
        $this->assertSame([], $route->getPatterns());
    }

    public function testPutWithOptionalParams(): void
    {
        $routeName           = Fixtures\TestRoute::getTestRouteName();
        $routePath           = Fixtures\TestRoute::getTestRoutePath();
        $routeRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();
        $routeMiddlewares    = Fixtures\TestRoute::getTestRouteMiddlewares();
        $routeAttributes     = Fixtures\TestRoute::getTestRouteAttributes();

        $collector = new RouteCollector();

        $route = $collector->put(
            $routeName,
            $routePath,
            $routeRequestHandler
        )
        ->addMiddleware(...$routeMiddlewares)
        ->setArguments($routeAttributes);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame([$collector::METHOD_PUT], $route->getMethods());
        $this->assertSame($routeRequestHandler, $route->getController());
        $this->assertSame($routeMiddlewares, $route->getMiddlewares());
        $this->assertSame($routeAttributes, $route->getArguments());
    }

    public function testPatch(): void
    {
        $routeName           = Fixtures\TestRoute::getTestRouteName();
        $routePath           = Fixtures\TestRoute::getTestRoutePath();
        $routeRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();

        $collector = new RouteCollector();

        $route = $collector->patch(
            $routeName,
            $routePath,
            $routeRequestHandler
        );

        $this->assertInstanceOf(RouteInterface::class, $route);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame([$collector::METHOD_PATCH], $route->getMethods());
        $this->assertSame($routeRequestHandler, $route->getController());

        // default property values...
        $this->assertSame([], $route->getMiddlewares());
        $this->assertSame([], $route->getArguments());
        $this->assertSame([], $route->getDefaults());
        $this->assertSame([], $route->getSchemes());
        $this->assertSame([], $route->getPatterns());
    }

    public function testPatchWithOptionalParams(): void
    {
        $routeName           = Fixtures\TestRoute::getTestRouteName();
        $routePath           = Fixtures\TestRoute::getTestRoutePath();
        $routeRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();
        $routeMiddlewares    = Fixtures\TestRoute::getTestRouteMiddlewares();
        $routeAttributes     = Fixtures\TestRoute::getTestRouteAttributes();

        $collector = new RouteCollector();

        $route = $collector->patch(
            $routeName,
            $routePath,
            $routeRequestHandler
        )
        ->addMiddleware(...$routeMiddlewares)
        ->setArguments($routeAttributes);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame([$collector::METHOD_PATCH], $route->getMethods());
        $this->assertSame($routeRequestHandler, $route->getController());
        $this->assertSame($routeMiddlewares, $route->getMiddlewares());
        $this->assertSame($routeAttributes, $route->getArguments());
    }

    public function testDelete(): void
    {
        $routeName           = Fixtures\TestRoute::getTestRouteName();
        $routePath           = Fixtures\TestRoute::getTestRoutePath();
        $routeRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();

        $collector = new RouteCollector();

        $route = $collector->delete(
            $routeName,
            $routePath,
            $routeRequestHandler
        );

        $this->assertInstanceOf(RouteInterface::class, $route);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame([$collector::METHOD_DELETE], $route->getMethods());
        $this->assertSame($routeRequestHandler, $route->getController());

        // default property values...
        $this->assertSame([], $route->getMiddlewares());
        $this->assertSame([], $route->getArguments());
        $this->assertSame([], $route->getDefaults());
        $this->assertSame([], $route->getSchemes());
        $this->assertSame([], $route->getPatterns());
    }

    public function testDeleteWithOptionalParams(): void
    {
        $routeName           = Fixtures\TestRoute::getTestRouteName();
        $routePath           = Fixtures\TestRoute::getTestRoutePath();
        $routeRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();
        $routeMiddlewares    = Fixtures\TestRoute::getTestRouteMiddlewares();
        $routeAttributes     = Fixtures\TestRoute::getTestRouteAttributes();

        $collector = new RouteCollector();

        $route = $collector->delete(
            $routeName,
            $routePath,
            $routeRequestHandler
        )
        ->addMiddleware(...$routeMiddlewares)
        ->setArguments($routeAttributes);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame([$collector::METHOD_DELETE], $route->getMethods());
        $this->assertSame($routeRequestHandler, $route->getController());
        $this->assertSame($routeMiddlewares, $route->getMiddlewares());
        $this->assertSame($routeAttributes, $route->getArguments());
    }

    public function testOptions(): void
    {
        $routeName           = Fixtures\TestRoute::getTestRouteName();
        $routePath           = Fixtures\TestRoute::getTestRoutePath();
        $routeRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();

        $collector = new RouteCollector();

        $route = $collector->options(
            $routeName,
            $routePath,
            $routeRequestHandler
        );

        $this->assertInstanceOf(RouteInterface::class, $route);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame([$collector::METHOD_OPTIONS], $route->getMethods());
        $this->assertSame($routeRequestHandler, $route->getController());

        // default property values...
        $this->assertSame([], $route->getMiddlewares());
        $this->assertSame([], $route->getArguments());
        $this->assertSame([], $route->getDefaults());
        $this->assertSame([], $route->getSchemes());
        $this->assertSame([], $route->getPatterns());
    }

    public function testOptionsWithOptionalParams(): void
    {
        $routeName           = Fixtures\TestRoute::getTestRouteName();
        $routePath           = Fixtures\TestRoute::getTestRoutePath();
        $routeRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();
        $routeMiddlewares    = Fixtures\TestRoute::getTestRouteMiddlewares();
        $routeAttributes     = Fixtures\TestRoute::getTestRouteAttributes();

        $collector = new RouteCollector();

        $route = $collector->options(
            $routeName,
            $routePath,
            $routeRequestHandler
        )
        ->addMiddleware(...$routeMiddlewares)
        ->setArguments($routeAttributes);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame([$collector::METHOD_OPTIONS], $route->getMethods());
        $this->assertSame($routeRequestHandler, $route->getController());
        $this->assertSame($routeMiddlewares, $route->getMiddlewares());
        $this->assertSame($routeAttributes, $route->getArguments());
    }

    public function testAny(): void
    {
        $routeName           = Fixtures\TestRoute::getTestRouteName();
        $routePath           = Fixtures\TestRoute::getTestRoutePath();
        $routeRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();

        $collector = new RouteCollector();

        $route = $collector->any(
            $routeName,
            $routePath,
            $routeRequestHandler
        );

        $this->assertInstanceOf(RouteInterface::class, $route);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame($collector::HTTP_METHODS_STANDARD, $route->getMethods());
        $this->assertSame($routeRequestHandler, $route->getController());

        // default property values...
        $this->assertSame([], $route->getMiddlewares());
        $this->assertSame([], $route->getArguments());
        $this->assertSame([], $route->getDefaults());
        $this->assertSame([], $route->getSchemes());
        $this->assertSame([], $route->getPatterns());
    }

    public function testAnyWithOptionalParams(): void
    {
        $routeName           = Fixtures\TestRoute::getTestRouteName();
        $routePath           = Fixtures\TestRoute::getTestRoutePath();
        $routeRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();
        $routeMiddlewares    = Fixtures\TestRoute::getTestRouteMiddlewares();
        $routeAttributes     = Fixtures\TestRoute::getTestRouteAttributes();

        $collector = new RouteCollector();

        $route = $collector->any(
            $routeName,
            $routePath,
            $routeRequestHandler
        )
        ->addMiddleware(...$routeMiddlewares)
        ->setArguments($routeAttributes);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame($collector::HTTP_METHODS_STANDARD, $route->getMethods());
        $this->assertSame($routeRequestHandler, $route->getController());
        $this->assertSame($routeMiddlewares, $route->getMiddlewares());
        $this->assertSame($routeAttributes, $route->getArguments());
    }

    public function testGroup(): void
    {
        $collector = new RouteCollector();
        $collector->get('home', '/', new Fixtures\BlankRequestHandler());

        $collector->group(function (RouteCollector $group): void {
            $group->get('home', '/', new Fixtures\BlankRequestHandler());
            $group->get('ping', '/ping', new Fixtures\BlankRequestHandler());

            $group->group(function (RouteCollector $group): void {
                $group->head('hello', 'hello', new Fixtures\BlankRequestHandler())
                    ->setArguments(['hello']);
            })
            ->addScheme('https', 'http')
            ->addMethod($group::METHOD_CONNECT)
            ->setDefaults(['hello' => 'world']);

            $group->group(function (RouteCollector $group): void {
                $group->group(function (RouteCollector $group): void {
                    $group->post('section.create', '/create', new Fixtures\BlankRequestHandler());
                    $group->patch('section.update', '/update/{id}', new Fixtures\BlankRequestHandler());
                })->addPrefix('/section')->addMiddleware(Fixtures\BlankMiddleware::class);

                $group->group(function (RouteCollector $group): void {
                    $group->post('product.create', 'create', new Fixtures\BlankRequestHandler());
                    $group->patch('product.update', '/update/{id}', new Fixtures\BlankRequestHandler());
                })
                ->addPrefix('product/');
            })
            ->addPrefix('/v1')->addDomain('https://youtube.com');
        })
        ->addPrefix('/api')->setName('api.');

        $collector->get('about-us', '/about-us', new Fixtures\BlankRequestHandler());

        $routes = $collector->getCollection();

        $this->assertContains([
            'name'        => 'home',
            'path'        => '/',
            'domain'      => '',
            'methods'     => [RouteCollector::METHOD_GET, RouteCollector::METHOD_HEAD],
            'handler'     => Fixtures\BlankRequestHandler::class,
            'middlewares' => [],
            'schemes'     => [],
            'defaults'    => [],
            'patterns'    => [],
            'arguments'   => [],
        ], Fixtures\Helper::routesToArray($routes));

        $this->assertContains([
            'name'        => 'api.home',
            'path'        => '/api/',
            'domain'      => '',
            'methods'     => [RouteCollector::METHOD_GET, RouteCollector::METHOD_HEAD],
            'handler'     => Fixtures\BlankRequestHandler::class,
            'middlewares' => [],
            'schemes'     => [],
            'defaults'    => [],
            'patterns'    => [],
            'arguments'   => [],
        ], Fixtures\Helper::routesToArray($routes));

        $this->assertContains([
            'name'        => 'api.ping',
            'path'        => '/api/ping',
            'domain'      => '',
            'methods'     => [RouteCollector::METHOD_GET, RouteCollector::METHOD_HEAD],
            'handler'     => Fixtures\BlankRequestHandler::class,
            'middlewares' => [],
            'schemes'     => [],
            'defaults'    => [],
            'patterns'    => [],
            'arguments'   => [],
        ], Fixtures\Helper::routesToArray($routes));

        $this->assertContains([
            'name'        => 'api.hello',
            'path'        => '/api/hello',
            'domain'      => '',
            'methods'     => [RouteCollector::METHOD_HEAD, RouteCollector::METHOD_CONNECT],
            'handler'     => Fixtures\BlankRequestHandler::class,
            'middlewares' => [],
            'schemes'     => ['https', 'http'],
            'defaults'    => ['hello' => 'world'],
            'patterns'    => [],
            'arguments'   => [],
        ], Fixtures\Helper::routesToArray($routes));

        $this->assertContains([
            'name'        => 'api.section.create',
            'path'        => '/api/v1/section/create',
            'domain'      => 'youtube.com',
            'methods'     => [RouteCollector::METHOD_POST],
            'handler'     => Fixtures\BlankRequestHandler::class,
            'middlewares' => [Fixtures\BlankMiddleware::class],
            'schemes'     => ['https'],
            'defaults'    => [],
            'patterns'    => [],
            'arguments'   => [],
        ], Fixtures\Helper::routesToArray($routes));

        $this->assertContains([
            'name'        => 'api.section.update',
            'path'        => '/api/v1/section/update/{id}',
            'domain'      => 'youtube.com',
            'methods'     => [RouteCollector::METHOD_PATCH],
            'handler'     => Fixtures\BlankRequestHandler::class,
            'middlewares' => [Fixtures\BlankMiddleware::class],
            'schemes'     => ['https'],
            'defaults'    => [],
            'patterns'    => [],
            'arguments'   => [],
        ], Fixtures\Helper::routesToArray($routes));

        $this->assertContains([
            'name'        => 'api.product.create',
            'path'        => '/api/v1/product/create',
            'domain'      => 'youtube.com',
            'methods'     => [RouteCollector::METHOD_POST],
            'handler'     => Fixtures\BlankRequestHandler::class,
            'middlewares' => [],
            'schemes'     => ['https'],
            'defaults'    => [],
            'patterns'    => [],
            'arguments'   => [],
        ], Fixtures\Helper::routesToArray($routes));

        $this->assertContains([
            'name'        => 'api.product.update',
            'path'        => '/api/v1/product/update/{id}',
            'domain'      => 'youtube.com',
            'methods'     => [RouteCollector::METHOD_PATCH],
            'handler'     => Fixtures\BlankRequestHandler::class,
            'middlewares' => [],
            'schemes'     => ['https'],
            'defaults'    => [],
            'patterns'    => [],
            'arguments'   => [],
        ], Fixtures\Helper::routesToArray($routes));

        $this->assertContains([
            'name'        => 'about-us',
            'path'        => '/about-us',
            'domain'      => '',
            'methods'     => [RouteCollector::METHOD_GET, RouteCollector::METHOD_HEAD],
            'handler'     => Fixtures\BlankRequestHandler::class,
            'middlewares' => [],
            'schemes'     => [],
            'defaults'    => [],
            'patterns'    => [],
            'arguments'   => [],
        ], Fixtures\Helper::routesToArray($routes));
    }

    public function testResource(): void
    {
        $routeName     = Fixtures\TestRoute::getTestRouteName();
        $routePath     = Fixtures\TestRoute::getTestRoutePath();
        $routeResource = new Fixtures\BlankRestful();

        $collector = new RouteCollector();

        $route = $collector->resource(
            $routeName,
            $routePath,
            $routeResource
        );

        $this->assertSame($routeName . '__restful', $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame($collector::HTTP_METHODS_STANDARD, $route->getMethods());
        $this->assertSame([$routeResource, $routeName], $route->getController());
    }

    public function testResourceWithException(): void
    {
        $routeName           = Fixtures\TestRoute::getTestRouteName();
        $routePath           = Fixtures\TestRoute::getTestRoutePath();
        $routeRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();

        $collector = new RouteCollector();

        $this->expectException(InvalidControllerException::class);
        $this->getExpectedExceptionMessage('Resource handler type should be a string or object, but not a callable');

        $collector->resource(
            $routeName,
            $routePath,
            $routeRequestHandler
        );
    }
}
