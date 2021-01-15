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
use Flight\Routing\Interfaces\RouteInterface;
use Flight\Routing\Interfaces\RouteListInterface;
use Flight\Routing\Route;
use Flight\Routing\RouteList;
use Nyholm\Psr7\ServerRequest;

/**
 * RouteListTest
 */
class RouteListTest extends BaseTestCase
{
    public function testAdd(): void
    {
        $route = new Fixtures\TestRoute();
        $route->setName('foo');

        $collection = new RouteList();
        $collection->add($route);

        $this->assertEquals([0 => $route], $collection->getRoutes());
        $this->assertEquals($route, \current($collection->getRoutes()));
        $this->assertCount(1, $collection->getRoutes());
    }

    public function testCannotOverriddenRoute(): void
    {
        $collection = new RouteList();
        $collection->add(new Route('foo', [Route::METHOD_GET], '/foo', null));
        $collection->add(new Route('foo', [Route::METHOD_GET], '/foo1', null));

        $routes = $collection->getRoutes();

        $this->assertEquals('/foo', \current($routes)->getPath());
        $this->assertEquals('/foo1', \end($routes)->getPath());
    }

    public function testDeepOverriddenRoute(): void
    {
        $collection = new RouteList();
        $collection->add(new Route('foo', [Route::METHOD_GET], '/foo', null));

        $collection1 = new RouteList();
        $collection1->add(new Route('foo', [Route::METHOD_GET], '/foo1', null));

        $collection2 = new RouteList();
        $collection2->add(new Route('foo', [Route::METHOD_GET], '/foo2', null));

        $collection1->addCollection($collection2);
        $collection->addCollection($collection1);

        $this->assertEquals('/foo2', \current($collection1->getRoutes())->getPath());
        $this->assertEquals('/foo2', \current($collection->getRoutes())->getPath());
    }

    public function testAddRoute(): void
    {
        $routeName           = Fixtures\TestRoute::getTestRouteName();
        $routePath           = Fixtures\TestRoute::getTestRoutePath();
        $routeMethods        = Fixtures\TestRoute::getTestRouteMethods();
        $routeRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();

        $collector = new RouteList();

        $route = $collector->addRoute(
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

        $collector = new RouteList();

        $route = $collector->addRoute(
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

        $collector = new RouteList();

        $route = $collector->head(
            $routeName,
            $routePath,
            $routeRequestHandler
        );

        $this->assertInstanceOf(RouteInterface::class, $route);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame([Route::METHOD_HEAD], $route->getMethods());
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

        $collector = new RouteList();

        $route = $collector->head(
            $routeName,
            $routePath,
            $routeRequestHandler
        )
        ->addMiddleware(...$routeMiddlewares)
        ->setArguments($routeAttributes);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame([Route::METHOD_HEAD], $route->getMethods());
        $this->assertSame($routeRequestHandler, $route->getController());
        $this->assertSame($routeMiddlewares, $route->getMiddlewares());
        $this->assertSame($routeAttributes, $route->getArguments());
    }

    public function testGet(): void
    {
        $routeName           = Fixtures\TestRoute::getTestRouteName();
        $routePath           = Fixtures\TestRoute::getTestRoutePath();
        $routeRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();

        $collector = new RouteList();

        $route = $collector->get(
            $routeName,
            $routePath,
            $routeRequestHandler
        );

        $this->assertInstanceOf(RouteInterface::class, $route);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame([Route::METHOD_GET, Route::METHOD_HEAD], $route->getMethods());
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

        $collector = new RouteList();

        $route = $collector->get(
            $routeName,
            $routePath,
            $routeRequestHandler
        )
        ->addMiddleware(...$routeMiddlewares)
        ->setArguments($routeAttributes);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame([Route::METHOD_GET, Route::METHOD_HEAD], $route->getMethods());
        $this->assertSame($routeRequestHandler, $route->getController());
        $this->assertSame($routeMiddlewares, $route->getMiddlewares());
        $this->assertSame($routeAttributes, $route->getArguments());
    }

    public function testPost(): void
    {
        $routeName           = Fixtures\TestRoute::getTestRouteName();
        $routePath           = Fixtures\TestRoute::getTestRoutePath();
        $routeRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();

        $collector = new RouteList();

        $route = $collector->post(
            $routeName,
            $routePath,
            $routeRequestHandler
        );

        $this->assertInstanceOf(RouteInterface::class, $route);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame([Route::METHOD_POST], $route->getMethods());
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

        $collector = new RouteList();

        $route = $collector->post(
            $routeName,
            $routePath,
            $routeRequestHandler
        )
        ->addMiddleware(...$routeMiddlewares)
        ->setArguments($routeAttributes);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame([Route::METHOD_POST], $route->getMethods());
        $this->assertSame($routeRequestHandler, $route->getController());
        $this->assertSame($routeMiddlewares, $route->getMiddlewares());
        $this->assertSame($routeAttributes, $route->getArguments());
    }

    public function testPut(): void
    {
        $routeName           = Fixtures\TestRoute::getTestRouteName();
        $routePath           = Fixtures\TestRoute::getTestRoutePath();
        $routeRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();

        $collector = new RouteList();

        $route = $collector->put(
            $routeName,
            $routePath,
            $routeRequestHandler
        );

        $this->assertInstanceOf(RouteInterface::class, $route);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame([Route::METHOD_PUT], $route->getMethods());
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

        $collector = new RouteList();

        $route = $collector->put(
            $routeName,
            $routePath,
            $routeRequestHandler
        )
        ->addMiddleware(...$routeMiddlewares)
        ->setArguments($routeAttributes);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame([Route::METHOD_PUT], $route->getMethods());
        $this->assertSame($routeRequestHandler, $route->getController());
        $this->assertSame($routeMiddlewares, $route->getMiddlewares());
        $this->assertSame($routeAttributes, $route->getArguments());
    }

    public function testPatch(): void
    {
        $routeName           = Fixtures\TestRoute::getTestRouteName();
        $routePath           = Fixtures\TestRoute::getTestRoutePath();
        $routeRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();

        $collector = new RouteList();

        $route = $collector->patch(
            $routeName,
            $routePath,
            $routeRequestHandler
        );

        $this->assertInstanceOf(RouteInterface::class, $route);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame([Route::METHOD_PATCH], $route->getMethods());
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

        $collector = new RouteList();

        $route = $collector->patch(
            $routeName,
            $routePath,
            $routeRequestHandler
        )
        ->addMiddleware(...$routeMiddlewares)
        ->setArguments($routeAttributes);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame([Route::METHOD_PATCH], $route->getMethods());
        $this->assertSame($routeRequestHandler, $route->getController());
        $this->assertSame($routeMiddlewares, $route->getMiddlewares());
        $this->assertSame($routeAttributes, $route->getArguments());
    }

    public function testDelete(): void
    {
        $routeName           = Fixtures\TestRoute::getTestRouteName();
        $routePath           = Fixtures\TestRoute::getTestRoutePath();
        $routeRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();

        $collector = new RouteList();

        $route = $collector->delete(
            $routeName,
            $routePath,
            $routeRequestHandler
        );

        $this->assertInstanceOf(RouteInterface::class, $route);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame([Route::METHOD_DELETE], $route->getMethods());
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

        $collector = new RouteList();

        $route = $collector->delete(
            $routeName,
            $routePath,
            $routeRequestHandler
        )
        ->addMiddleware(...$routeMiddlewares)
        ->setArguments($routeAttributes);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame([Route::METHOD_DELETE], $route->getMethods());
        $this->assertSame($routeRequestHandler, $route->getController());
        $this->assertSame($routeMiddlewares, $route->getMiddlewares());
        $this->assertSame($routeAttributes, $route->getArguments());
    }

    public function testOptions(): void
    {
        $routeName           = Fixtures\TestRoute::getTestRouteName();
        $routePath           = Fixtures\TestRoute::getTestRoutePath();
        $routeRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();

        $collector = new RouteList();

        $route = $collector->options(
            $routeName,
            $routePath,
            $routeRequestHandler
        );

        $this->assertInstanceOf(RouteInterface::class, $route);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame([Route::METHOD_OPTIONS], $route->getMethods());
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

        $collector = new RouteList();

        $route = $collector->options(
            $routeName,
            $routePath,
            $routeRequestHandler
        )
        ->addMiddleware(...$routeMiddlewares)
        ->setArguments($routeAttributes);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame([Route::METHOD_OPTIONS], $route->getMethods());
        $this->assertSame($routeRequestHandler, $route->getController());
        $this->assertSame($routeMiddlewares, $route->getMiddlewares());
        $this->assertSame($routeAttributes, $route->getArguments());
    }

    public function testAny(): void
    {
        $routeName           = Fixtures\TestRoute::getTestRouteName();
        $routePath           = Fixtures\TestRoute::getTestRoutePath();
        $routeRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();

        $collector = new RouteList();

        $route = $collector->any(
            $routeName,
            $routePath,
            $routeRequestHandler
        );

        $this->assertInstanceOf(RouteInterface::class, $route);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame(Route::HTTP_METHODS_STANDARD, $route->getMethods());
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

        $collector = new RouteList();

        $route = $collector->any(
            $routeName,
            $routePath,
            $routeRequestHandler
        )
        ->addMiddleware(...$routeMiddlewares)
        ->setArguments($routeAttributes);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame(Route::HTTP_METHODS_STANDARD, $route->getMethods());
        $this->assertSame($routeRequestHandler, $route->getController());
        $this->assertSame($routeMiddlewares, $route->getMiddlewares());
        $this->assertSame($routeAttributes, $route->getArguments());
    }

    public function testGroup(): void
    {
        $collector = new RouteList();
        $collector->get('home', '/', new Fixtures\BlankRequestHandler());

        $collector->group(function (RouteListInterface $group): void {
            $group->get('home', '/', new Fixtures\BlankRequestHandler());
            $group->get('ping', '/ping', new Fixtures\BlankRequestHandler());

            $group->group(function (RouteListInterface $group): void {
                $group->head('hello', 'hello', new Fixtures\BlankRequestHandler())
                    ->setArguments(['hello']);
            })
            ->addScheme('https', 'http')
            ->addMethod(Route::METHOD_CONNECT)
            ->setDefaults(['hello' => 'world']);

            $group->group(function (RouteListInterface $group): void {
                $group->group(function (RouteListInterface $group): void {
                    $group->post('section.create', '/create', new Fixtures\BlankRequestHandler());
                    $group->patch('section.update', '/update/{id}', new Fixtures\BlankRequestHandler());
                })->addPrefix('/section')->addMiddleware(Fixtures\BlankMiddleware::class);

                $group->group(function (RouteListInterface $group): void {
                    $group->post('product.create', 'create', new Fixtures\BlankRequestHandler());
                    $group->patch('product.update', '/update/{id}', new Fixtures\BlankRequestHandler());
                })
                ->addPrefix('product/');
            })
            ->addPrefix('/v1')->addDomain('https://youtube.com');
        })
        ->addPrefix('/api')->setName('api.');

        $collector->get('about-us', '/about-us', new Fixtures\BlankRequestHandler());

        $routes = $collector->getRoutes();

        $this->assertContains([
            'name'        => 'home',
            'path'        => '/',
            'domain'      => '',
            'methods'     => [Route::METHOD_GET, Route::METHOD_HEAD],
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
            'methods'     => [Route::METHOD_GET, Route::METHOD_HEAD],
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
            'methods'     => [Route::METHOD_GET, Route::METHOD_HEAD],
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
            'methods'     => [Route::METHOD_HEAD, Route::METHOD_CONNECT],
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
            'methods'     => [Route::METHOD_POST],
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
            'methods'     => [Route::METHOD_PATCH],
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
            'methods'     => [Route::METHOD_POST],
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
            'methods'     => [Route::METHOD_PATCH],
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
            'methods'     => [Route::METHOD_GET, Route::METHOD_HEAD],
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

        $collector = new RouteList();

        $collector->resource(
            $routeName,
            $routePath,
            $routeResource
        );

        $route = \current($collector->getRoutes());

        $this->assertSame($routeName . '__restful', $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame(Route::HTTP_METHODS_STANDARD, $route->getMethods());
        $this->assertSame([$routeResource, $routeName], $route->getController());
    }

    public function testResourceWithException(): void
    {
        $routeName           = Fixtures\TestRoute::getTestRouteName();
        $routePath           = Fixtures\TestRoute::getTestRoutePath();
        $routeRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();

        $collector = new RouteList();

        $this->expectException(InvalidControllerException::class);
        $this->getExpectedExceptionMessage('Resource handler type should be a string or object, but not a callable');

        $collector->resource(
            $routeName,
            $routePath,
            $routeRequestHandler
        );
    }

    /**
     * @dataProvider provideCollectionData
     *
     * @param bool $cached
     */
    public function testCollectionWithAndCache(bool $cached): void
    {
        $demoCollection = new RouteList();
        $demoCollection->add(new Route('a', [Route::METHOD_POST], '/admin/post/', null));
        $demoCollection->add(new Route('b', [Route::METHOD_POST], '/admin/post/new', null));
        $demoCollection->add((new Route('c', [Route::METHOD_POST], '/admin/post/{id}', null))->addPattern('id', '\d+'));
        $demoCollection->add((new Route('d', [Route::METHOD_PATCH], '/admin/post/{id}/edit', null))->addPattern('id', '\d+'));
        $demoCollection->add((new Route('e', [Route::METHOD_DELETE], '/admin/post/{id}/delete', null))->addPattern('id', '\d+'));
        $demoCollection->add(new Route('f', [Route::METHOD_GET], '/blog/', null));
        $demoCollection->add(new Route('g', [Route::METHOD_GET], '/blog/rss.xml', null));
        $demoCollection->add((new Route('h', [Route::METHOD_GET], '/blog/page/{page}', null))->addPattern('id', '\d+'));
        $demoCollection->add((new Route('i', [Route::METHOD_GET], '/blog/posts/{page}', null))->addPattern('id', '\d+'));
        $demoCollection->add((new Route('j', [Route::METHOD_GET], '/blog/comments/{id}/new', null))->addPattern('id', '\d+'));
        $demoCollection->add(new Route('k', [Route::METHOD_GET], '/blog/search', null));
        $demoCollection->add(new Route('l', [Route::METHOD_POST], '/login', null));
        $demoCollection->add(new Route('m', [Route::METHOD_POST], '/logout', null));
        $demoCollection->withPrefix('/{_locale}');
        $demoCollection->add(new Route('n', [Route::METHOD_GET], '/{_locale}', null));
        $demoCollection->withPatterns(['_locale' => 'en|fr']);
        $demoCollection->withDefaults(['_locale' => 'en']);
        $demoCollection->withName('demo.');
        $demoCollection->withMethod(Route::METHOD_CONNECT);

        $chunkedCollection = new RouteList();

        for ($i = 0; $i < 1000; ++$i) {
            $h = \substr(\md5((string) $i), 0, 6);
            $chunkedCollection->get('_' . $i, '/' . $h . '/{a}/{b}/{c}/' . $h, null);
        }
        $chunkedCollection->withDomain('http://localhost');
        $chunkedCollection->withScheme('https', 'http');
        $chunkedCollection->withMiddleware(Fixtures\BlankMiddleware::class);

        $groupOptimisedCollection = new RouteList();
        $groupOptimisedCollection->addRoute('a_first', [Route::METHOD_GET], '/a/11', null);
        $groupOptimisedCollection->addRoute('a_second', [Route::METHOD_GET], '/a/22', null);
        $groupOptimisedCollection->addRoute('a_third', [Route::METHOD_GET], '/a/333', null);
        $groupOptimisedCollection->addRoute('a_wildcard', [Route::METHOD_GET], '/{param}', null);
        $groupOptimisedCollection->addRoute('a_fourth', [Route::METHOD_GET], '/a/44/', null);
        $groupOptimisedCollection->addRoute('a_fifth', [Route::METHOD_GET], '/a/55/', null);
        $groupOptimisedCollection->addRoute('nested_wildcard', [Route::METHOD_GET], '/nested/{param}', null);
        $groupOptimisedCollection->addRoute('nested_a', [Route::METHOD_GET], '/nested/group/a/', null);
        $groupOptimisedCollection->addRoute('nested_b', [Route::METHOD_GET], '/nested/group/b/', null);
        $groupOptimisedCollection->addRoute('nested_c', [Route::METHOD_GET], '/nested/group/c/', null);
        $testRoute = $groupOptimisedCollection->addRoute('a_sixth', [Route::METHOD_GET], '/a/66/', Fixtures\BlankController::class);

        $groupOptimisedCollection->addRoute('slashed_a', [Route::METHOD_GET], '/slashed/group/', null);
        $groupOptimisedCollection->addRoute('slashed_b', [Route::METHOD_GET], '/slashed/group/b/', null);
        $groupOptimisedCollection->addRoute('slashed_c', [Route::METHOD_GET], '/slashed/group/c/', null);

        $mergedCollection = new RouteList();
        $mergedCollection->addForeach(...$demoCollection);
        $mergedCollection->addForeach(...$groupOptimisedCollection->getIterator());
        $mergedCollection->addForeach(...$chunkedCollection->getRoutes());

        $router = $this->getRouter();
        $cacheFile = __DIR__ . '/Fixtures/routes/cache_router.php';

        if ($cached) {
            $router->warmRoutes($cacheFile, false);
        }

        $router->addRoute(...$demoCollection);
        $router->addRoute(...$groupOptimisedCollection->getRoutes());
        $router->addRoute(...$chunkedCollection->getIterator()->getArrayCopy());

        if ($cached) {
            $router->warmRoutes($cacheFile);
            $this->assertNotEmpty($router->getCompiledRoutes());
        }

        $this->assertCount(1028, $mergedCollection);
        $this->assertEquals($mergedCollection->getRoutes(), $router->getRoutes());

        $route = $router->match(new ServerRequest(current($testRoute->getMethods()), $testRoute->getPath()));

        $this->assertInstanceOf(RouteInterface::class, $route);
    }

    /**
     * @return string[]
     */
    public function provideCollectionData(): array
    {
        return [[false], [true]];
    }
}
