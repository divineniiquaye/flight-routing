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

use BadMethodCallException;
use Flight\Routing\Interfaces\MatcherDumperInterface;
use Flight\Routing\Interfaces\RouteMatcherInterface;
use Flight\Routing\Route;
use Flight\Routing\RouteCollection;
use Flight\Routing\Router;
use LogicException;
use Nyholm\Psr7\ServerRequest;

/**
 * RouteCollectionTest
 */
class RouteCollectionTest extends BaseTestCase
{
    public function testAdd(): void
    {
        $route = new Fixtures\TestRoute();
        $route->bind('foo');

        $collection = new RouteCollection();
        $collection->add($route);

        $this->assertNotInstanceOf(Fixtures\TestRoute::class, \current($collection->getRoutes()));
        $this->assertCount(1, $collection->getRoutes());
    }

    public function testCannotOverriddenRoute(): void
    {
        $collection = new RouteCollection();
        $collection->add(new Route('/foo', Router::METHOD_GET));
        $collection->add(new Route('/foo1', Router::METHOD_GET));

        $routes = $collection->getRoutes();

        $this->assertEquals('/foo', \current($routes)->getPath());
        $this->assertEquals('/foo1', \end($routes)->getPath());
    }

    public function testCannotFindMethodInRoute(): void
    {
        $this->expectExceptionMessage(
            'Method call invalid, Flight\Routing\Route::get(\'exception\') should be a supported type.'
        );
        $this->expectException(BadMethodCallException::class);

        $collection = new RouteCollection();
        $collection->add(new Route('/foo', Router::METHOD_GET))->exception();
    }

    public function testCannotFindMethodInCollection(): void
    {
        $this->expectExceptionMessage('Method call invalid, arguments passed to \'exceptions\' method not suported.');
        $this->expectException(BadMethodCallException::class);

        $collection = new RouteCollection();
        $collection->add(new Route('/foo', Router::METHOD_GET))->withExceptions(['nothing']);
    }

    public function testDeepOverriddenRoute(): void
    {
        $collection = new RouteCollection();
        $collection->add(new Route('/foo', Router::METHOD_GET));

        $collection1 = new RouteCollection();
        $collection1->add(new Route('/foo1', Router::METHOD_GET));

        $collection2 = new RouteCollection();
        $collection2->add(new Route('foo2', Router::METHOD_GET));

        $collection1->add(...$collection2->getRoutes());
        $collection->add(...$collection->getRoutes());

        $this->assertEquals('/foo1', \current($collection1->getRoutes())->getPath());
        $this->assertEquals('/foo', \current($collection->getRoutes())->getPath());
    }

    public function testAddRoute(): void
    {
        $routeName           = Fixtures\TestRoute::getTestRouteName();
        $routePath           = Fixtures\TestRoute::getTestRoutePath();
        $routeMethods        = Fixtures\TestRoute::getTestRouteMethods();
        $routeRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();

        $collector = new RouteCollection();

        $route = $collector->addRoute(
            $routePath,
            $routeMethods,
            $routeRequestHandler
        );
        $route->bind($routeName);

        $this->assertInstanceOf(Route::class, $route);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame($routeMethods, \array_keys($route->getMethods()));
        $this->assertSame($routeRequestHandler, $route->getController());

        // default property values...
        $this->assertSame([], $route->getMiddlewares());
        $this->assertSame([], $route->getDefaults());
        $this->assertSame([], \array_keys($route->getSchemes()));
        $this->assertSame([], $route->getPatterns());
    }

    public function testRouteWithOptionalParams(): void
    {
        $routeName           = Fixtures\TestRoute::getTestRouteName();
        $routePath           = Fixtures\TestRoute::getTestRoutePath();
        $routeMethods        = Fixtures\TestRoute::getTestRouteMethods();
        $routeRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();
        $routeMiddlewares    = Fixtures\TestRoute::getTestRouteMiddlewares();

        $collector = new RouteCollection();

        $route = $collector->addRoute(
            $routePath,
            $routeMethods,
            $routeRequestHandler
        )
        ->bind($routeName)->middleware(...$routeMiddlewares);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame($routeMethods, \array_keys($route->getMethods()));
        $this->assertSame($routeRequestHandler, $route->getController());
        $this->assertSame($routeMiddlewares, $route->getMiddlewares());
    }

    public function testConflictingRouteNames(): void
    {
        $controllers = new RouteCollection();

        $mountedRootController = $controllers->get('/', function (): void {
        });

        $mainRootController = new Route('/');
        $mainRootController->bind($mainRootController->generateRouteName('main_1'));

        $controllers->getRoutes();

        $this->assertNotEquals($mainRootController->getName(), $mountedRootController->getName());
    }

    public function testUniqueGeneratedRouteNames(): void
    {
        $controllers = new RouteCollection();

        $controllers->addRoute('/a-a', [], function (): void {
        });
        $controllers->addRoute('/a_a', [], function (): void {
        });
        $controllers->addRoute('/a/a', [], function (): void {
        });

        $routes = $controllers->getRoutes();

        $this->assertCount(3, $routes);
        $this->assertEquals(['_a_a', '_a_a_1', '_a_a_2'], \array_map(
            function (Route $route) {
                return $route->getName();
            },
            $routes
        ));
    }

    public function testUniqueGeneratedRouteNamesAmongMounts(): void
    {
        $controllers = new RouteCollection();

        $controllers->group('', $rootA = new RouteCollection());
        $controllers->group('', $rootB = new RouteCollection());

        $rootA->addRoute('/leaf-a', [], function (): void {
        });
        $rootB->addRoute('/leaf_a', [], function (): void {
        });

        $routes = $controllers->getRoutes();

        $this->assertCount(2, $routes);
        $this->assertEquals(['_leaf_a', '_leaf_a_1'], \array_map(
            function (Route $route) {
                return $route->getName();
            },
            $routes
        ));
    }

    public function testHead(): void
    {
        $routeName           = Fixtures\TestRoute::getTestRouteName();
        $routePath           = Fixtures\TestRoute::getTestRoutePath();
        $routeRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();

        $collector = new RouteCollection();

        $route = $collector->head(
            $routePath,
            $routeRequestHandler
        )->bind($routeName);

        $this->assertInstanceOf(Route::class, $route);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame([Router::METHOD_HEAD], \array_keys($route->getMethods()));
        $this->assertSame($routeRequestHandler, $route->getController());

        // default property values...
        $this->assertSame([], $route->getMiddlewares());
        $this->assertSame([], $route->getDefaults());
        $this->assertSame([], \array_keys($route->getSchemes()));
        $this->assertSame([], $route->getPatterns());
    }

    public function testGet(): void
    {
        $routeName           = Fixtures\TestRoute::getTestRouteName();
        $routePath           = Fixtures\TestRoute::getTestRoutePath();
        $routeRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();

        $collector = new RouteCollection();

        $route = $collector->get(
            $routePath,
            $routeRequestHandler
        )->bind($routeName);

        $this->assertInstanceOf(Route::class, $route);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame([Router::METHOD_GET, Router::METHOD_HEAD], \array_keys($route->getMethods()));
        $this->assertSame($routeRequestHandler, $route->getController());

        // default property values...
        $this->assertSame([], $route->getMiddlewares());
        $this->assertSame([], $route->getDefaults());
        $this->assertSame([], \array_keys($route->getSchemes()));
        $this->assertSame([], $route->getPatterns());
    }

    public function testPost(): void
    {
        $routeName           = Fixtures\TestRoute::getTestRouteName();
        $routePath           = Fixtures\TestRoute::getTestRoutePath();
        $routeRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();

        $collector = new RouteCollection();

        $route = $collector->post(
            $routePath,
            $routeRequestHandler
        )->bind($routeName);

        $this->assertInstanceOf(Route::class, $route);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame([Router::METHOD_POST], \array_keys($route->getMethods()));
        $this->assertSame($routeRequestHandler, $route->getController());

        // default property values...
        $this->assertSame([], $route->getMiddlewares());
        $this->assertSame([], $route->getDefaults());
        $this->assertSame([], \array_keys($route->getSchemes()));
        $this->assertSame([], $route->getPatterns());
    }

    public function testPut(): void
    {
        $routeName           = Fixtures\TestRoute::getTestRouteName();
        $routePath           = Fixtures\TestRoute::getTestRoutePath();
        $routeRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();

        $collector = new RouteCollection();

        $route = $collector->put(
            $routePath,
            $routeRequestHandler
        )->bind($routeName);

        $this->assertInstanceOf(Route::class, $route);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame([Router::METHOD_PUT], \array_keys($route->getMethods()));
        $this->assertSame($routeRequestHandler, $route->getController());

        // default property values...
        $this->assertSame([], $route->getMiddlewares());
        $this->assertSame([], $route->getDefaults());
        $this->assertSame([], \array_keys($route->getSchemes()));
        $this->assertSame([], $route->getPatterns());
    }

    public function testPatch(): void
    {
        $routeName           = Fixtures\TestRoute::getTestRouteName();
        $routePath           = Fixtures\TestRoute::getTestRoutePath();
        $routeRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();

        $collector = new RouteCollection();

        $route = $collector->patch(
            $routePath,
            $routeRequestHandler
        )->bind($routeName);

        $this->assertInstanceOf(Route::class, $route);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame([Router::METHOD_PATCH], \array_keys($route->getMethods()));
        $this->assertSame($routeRequestHandler, $route->getController());

        // default property values...
        $this->assertSame([], $route->getMiddlewares());
        $this->assertSame([], $route->getDefaults());
        $this->assertSame([], \array_keys($route->getSchemes()));
        $this->assertSame([], $route->getPatterns());
    }

    public function testDelete(): void
    {
        $routeName           = Fixtures\TestRoute::getTestRouteName();
        $routePath           = Fixtures\TestRoute::getTestRoutePath();
        $routeRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();

        $collector = new RouteCollection();

        $route = $collector->delete(
            $routePath,
            $routeRequestHandler
        )->bind($routeName);

        $this->assertInstanceOf(Route::class, $route);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame([Router::METHOD_DELETE], \array_keys($route->getMethods()));
        $this->assertSame($routeRequestHandler, $route->getController());

        // default property values...
        $this->assertSame([], $route->getMiddlewares());
        $this->assertSame([], $route->getDefaults());
        $this->assertSame([], \array_keys($route->getSchemes()));
        $this->assertSame([], $route->getPatterns());
    }

    public function testOptions(): void
    {
        $routeName           = Fixtures\TestRoute::getTestRouteName();
        $routePath           = Fixtures\TestRoute::getTestRoutePath();
        $routeRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();

        $collector = new RouteCollection();

        $route = $collector->options(
            $routePath,
            $routeRequestHandler
        )->bind($routeName);

        $this->assertInstanceOf(Route::class, $route);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame([Router::METHOD_OPTIONS], \array_keys($route->getMethods()));
        $this->assertSame($routeRequestHandler, $route->getController());

        // default property values...
        $this->assertSame([], $route->getMiddlewares());
        $this->assertSame([], $route->getDefaults());
        $this->assertSame([], \array_keys($route->getSchemes()));
        $this->assertSame([], $route->getPatterns());
    }

    public function testAny(): void
    {
        $routeName           = Fixtures\TestRoute::getTestRouteName();
        $routePath           = Fixtures\TestRoute::getTestRoutePath();
        $routeRequestHandler = Fixtures\TestRoute::getTestRouteRequestHandler();

        $collector = new RouteCollection();

        $route = $collector->any(
            $routePath,
            $routeRequestHandler
        );
        $route->bind($routeName);

        $this->assertInstanceOf(Route::class, $route);

        $this->assertSame($routeName, $route->getName());
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame(Router::HTTP_METHODS_STANDARD, \array_keys($route->getMethods()));
        $this->assertSame($routeRequestHandler, $route->getController());

        // default property values...
        $this->assertSame([], $route->getMiddlewares());
        $this->assertSame([], $route->getDefaults());
        $this->assertSame([], \array_keys($route->getSchemes()));
        $this->assertSame([], $route->getPatterns());
    }

    public function testGroupWithInvalidController(): void
    {
        $collector = new RouteCollection();

        $this->expectExceptionMessage('The "group" method takes either a "RouteCollection" instance or callable.');
        $this->expectException(LogicException::class);

        $collector->group('invalid', new Fixtures\TestRoute());
    }

    public function testDeepGrouping(): void
    {
        $collector = new RouteCollection();
        $collector->get('/', new Fixtures\BlankRequestHandler())->bind('home');

        $collector->group('api.', function (RouteCollection $group): void {
            $group->get('/', new Fixtures\BlankRequestHandler())->bind('home');
            $group->get('/ping', new Fixtures\BlankRequestHandler())->bind('ping');

            $group->group('', function (RouteCollection $group): void {
                $group->head('hello', new Fixtures\BlankRequestHandler())
                    ->bind('hello')->argument(0, 'hello');
            })
            ->withScheme('https', 'http')
            ->withMethod(Router::METHOD_CONNECT)
            ->withDefault('hello', 'world');

            $group->group('', function (RouteCollection $group): void {
                $group->group('', function (RouteCollection $group): void {
                    $group->post('/create', new Fixtures\BlankRequestHandler())->bind('section.create');
                    $group->patch('/update/{id}', new Fixtures\BlankRequestHandler())->bind('section.update');
                })->withPrefix('/section')->withMiddleware(Fixtures\BlankMiddleware::class);

                $group->group('', function (RouteCollection $group): void {
                    $group->post('/create', new Fixtures\BlankRequestHandler())->bind('product.create');
                    $group->patch('/update/{id}', new Fixtures\BlankRequestHandler())->bind('product.update');
                })
                ->withPrefix('product/');
            })
            ->withPrefix('/v1')->withDomain('https://youtube.com');
        })
        ->withPrefix('/api');

        $collector->get('/about-us', new Fixtures\BlankRequestHandler())->bind('about-us');

        $routes = $collector->getRoutes();

        $this->assertContains([
            'name'        => 'home',
            'path'        => '/',
            'domain'      => [],
            'methods'     => [Router::METHOD_GET, Router::METHOD_HEAD],
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
            'domain'      => [],
            'methods'     => [Router::METHOD_GET, Router::METHOD_HEAD],
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
            'domain'      => [],
            'methods'     => [Router::METHOD_GET, Router::METHOD_HEAD],
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
            'domain'      => [],
            'methods'     => [Router::METHOD_HEAD, Router::METHOD_CONNECT],
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
            'domain'      => ['youtube.com'],
            'methods'     => [Router::METHOD_POST],
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
            'domain'      => ['youtube.com'],
            'methods'     => [Router::METHOD_PATCH],
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
            'domain'      => ['youtube.com'],
            'methods'     => [Router::METHOD_POST],
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
            'domain'      => ['youtube.com'],
            'methods'     => [Router::METHOD_PATCH],
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
            'domain'      => [],
            'methods'     => [Router::METHOD_GET, Router::METHOD_HEAD],
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

        $collector = new RouteCollection();

        $collector->resource(
            $routeName,
            \ltrim($routePath, '/'),
            $routeResource
        );

        $route = \current($collector->getRoutes());

        $this->assertTrue(str_starts_with($route->getName(), 'HEAD_GET_POST_PUT_PATCH_DELETE_PURGE_OPTIONS_TRACE_CONNECT'));
        $this->assertEquals($routeName, $route->getDefaults()['_api']);
        $this->assertSame($routePath, $route->getPath());
        $this->assertSame(Router::HTTP_METHODS_STANDARD, \array_keys($route->getMethods()));
        $this->assertSame($routeResource, $route->getController());
    }

    public function testResourceWithException(): void
    {
        $routeName           = Fixtures\TestRoute::getTestRouteName();
        $routePath           = Fixtures\TestRoute::getTestRoutePath();

        $collector = new RouteCollection();

        //$this->expectException(InvalidControllerException::class);
        //$this->getExpectedExceptionMessage(
        //    'Resource handler type should be a class string or class object, but not a callable or array'
        //);

        $collector->resource(
            $routeName,
            $routePath,
            'Flight\Routing\Tests\Fixtures\BlankHandler'
        );
    }

    /**
     * @dataProvider provideCollectionData
     *
     * @param bool $cached
     */
    public function testCollectionGroupingAndWithCache(bool $cached): void
    {
        $router = $this->getRouter();
        $router->setOptions(['cache_dir' => __DIR__ . '/Fixtures/routes', 'debug' => $cached]);

        $routes = [];

        if (!$router->isFrozen()) {
            // Master collection
            $mergedCollection = new RouteCollection();

            // Collection without names
            $demoCollection = new RouteCollection();
            $demoCollection->add(new Route('/admin/post/', Router::METHOD_POST));
            $demoCollection->add(new Route('/admin/post/new', Router::METHOD_POST));
            $demoCollection->add((new Route('/admin/post/{id}', Router::METHOD_POST))->assert('id', '\d+'));
            $demoCollection->add((new Route('/admin/post/{id}/edit', Router::METHOD_PATCH))->assert('id', '\d+'));
            $demoCollection->add((new Route('/admin/post/{id}/delete', Router::METHOD_DELETE))->assert('id', '\d+'));
            $demoCollection->add(new Route('/blog/', Router::METHOD_GET));
            $demoCollection->add(new Route('/blog/rss.xml', Router::METHOD_GET));
            $demoCollection->add((new Route('/blog/page/{page}', Router::METHOD_GET))->assert('id', '\d+'));
            $demoCollection->add((new Route('/blog/posts/{page}', Router::METHOD_GET))->assert('id', '\d+'));
            $demoCollection->add((new Route('/blog/comments/{id}/new', Router::METHOD_GET))->assert('id', '\d+'));
            $demoCollection->add(new Route('/blog/search', Router::METHOD_GET));
            $demoCollection->add(new Route('/login', Router::METHOD_POST));
            $demoCollection->add(new Route('/logout', Router::METHOD_POST));
            $demoCollection->withPrefix('/{_locale}');
            $demoCollection->add($routes[] = new Route('/', Router::METHOD_GET));
            $demoCollection->withMethod(Router::METHOD_CONNECT);
            $mergedCollection->group('demo.', $demoCollection)->withDefault('_locale', 'en')->withAssert('_locale', 'en|fr');

            $chunkedCollection = new RouteCollection();
            $chunkedCollection->withDomain('http://localhost');
            $chunkedCollection->withScheme('https', 'http');
            $chunkedCollection->withMiddleware(Fixtures\BlankMiddleware::class);

            for ($i = 0; $i < 100; ++$i) {
                $h = \substr(\md5((string) $i), 0, 6);
                $chunkedCollection->get('/' . $h . '/{a}/{b}/{c}/' . $h)->bind('_' . $i);
            }
            $mergedCollection->group('chuck_', $chunkedCollection);

            $groupOptimisedCollection = new RouteCollection();
            $groupOptimisedCollection->addRoute('/a/11', [Router::METHOD_GET])->bind('a_first');
            $groupOptimisedCollection->addRoute('/a/22', [Router::METHOD_GET])->bind('a_second');
            $groupOptimisedCollection->addRoute('/a/333', [Router::METHOD_GET])->bind('a_third');
            $groupOptimisedCollection->addRoute('/{param}', [Router::METHOD_GET])->bind('a_wildcard');
            $groupOptimisedCollection->addRoute('/a/44/', [Router::METHOD_GET])->bind('a_fourth');
            $groupOptimisedCollection->addRoute('/a/55/', [Router::METHOD_GET])->bind('a_fifth');
            $routes[] = $groupOptimisedCollection->addRoute('/nested/{param}', [Router::METHOD_GET])->bind('nested_wildcard');
            $groupOptimisedCollection->addRoute('/nested/group/a/', [Router::METHOD_GET])->bind('nested_a');
            $groupOptimisedCollection->addRoute('/nested/group/b/', [Router::METHOD_GET])->bind('nested_b');
            $groupOptimisedCollection->addRoute('/nested/group/c/', [Router::METHOD_GET])->bind('nested_c');
            $routes[] = $groupOptimisedCollection->addRoute('a_sixth', [Router::METHOD_GET], '/a/66/', Fixtures\BlankController::class);

            $groupOptimisedCollection->addRoute('/slashed/group/', [Router::METHOD_GET])->bind('slashed_a');
            $groupOptimisedCollection->addRoute('/slashed/group/b/', [Router::METHOD_GET])->bind('slashed_b');
            $routes[] = $groupOptimisedCollection->addRoute('/slashed/group/c/', [Router::METHOD_GET])->bind('slashed_c');

            $mergedCollection->group('', $groupOptimisedCollection);

            $merged = $mergedCollection->getRoutes();
            $router->addRoute(...$merged);

            $this->assertCount(128, $router->getCollection());
            $this->assertSame($merged, $router->getCollection()->getIterator()->getArrayCopy());

            foreach ($routes as $testRoute) {
                if (str_starts_with($path = $testRoute->getPath(), '{_locale}')) {
                    $path = \str_replace('{_locale}', 'en', $path);
                }

                $route = $router->match(new ServerRequest(\current(\array_keys($testRoute->getMethods())), $path));

                $this->assertInstanceOf(Route::class, $route);
            }

            $this->assertInstanceOf(RouteMatcherInterface::class, $router->getMatcher());
        }

        $cacheFile = __DIR__ . '/Fixtures/routes/compiled_routes.php';

        if (\file_exists($cacheFile)) {
            if (!$cached) {
                \unlink($cacheFile);
            }

            $this->assertInstanceOf(MatcherDumperInterface::class, $router->getMatcher());
        }
    }

    /**
     * @return string[]
     */
    public function provideCollectionData(): array
    {
        return [[false], [true], [true], [false]];
    }
}
