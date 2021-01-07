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

/**
 * RouteListTest
 */
class RouteListTest extends BaseTestCase
{
    public function testAdd(): void
    {
        $route = new Fixtures\TestRoute();
        $route->setName('foo');

        $collector = new RouteList();
        $collector->add($route);

        $this->assertCount(1, $collector->getRoutes());
        $this->assertSame('foo', \current($collector->getRoutes())->getName());
    }

    public function testRoute(): void
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

        $route = current($collector->getRoutes());

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
}
