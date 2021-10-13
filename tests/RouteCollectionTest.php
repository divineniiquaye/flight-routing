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

namespace Flight\Routing\Tests;

use Flight\Routing\Route;
use Flight\Routing\Handlers\ResourceHandler;
use Flight\Routing\RouteCollection;
use Flight\Routing\RouteCompiler;
use Flight\Routing\Router;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

/**
 * RouteCollectionTest.
 */
class RouteCollectionTest extends TestCase
{
    /** @var string */
    private static $cacheFile = __DIR__ . '/Fixtures/routes/compiled.php';

    /**
     * {@inheritdoc}
     */
    public static function tearDownAfterClass(): void
    {
        if (\file_exists(self::$cacheFile)) {
            @\unlink(self::$cacheFile);
        }
    }

    public function testCollection(): void
    {
        $collection = new RouteCollection();
        $this->assertInstanceOf(\SplFixedArray::class, $collection->getRoutes());

        $this->expectExceptionMessage('Index invalid or out of range');
        $this->expectException(\RuntimeException::class);

        $collection->addRoute('/hello', ['GET']);
    }

    public function testAdd(): void
    {
        $collection = new RouteCollection();
        $collection->routes([new Route('/1'), new Route('/2'), new Route('/3')]);
        $collection = $this->getIterable($collection);

        $this->assertInstanceOf(Route::class, $route = $collection->current());
        $this->assertEquals([
            'handler' => null,
            'methods' => Route::DEFAULT_METHODS,
            'schemes' => [],
            'hosts' => [],
            'name' => null,
            'path' => '/1',
            'patterns' => [],
            'arguments' => [],
            'defaults' => [],
        ], Fixtures\Helper::routesToArray([$route], true));

        $collection->next();
        $this->assertInstanceOf(Route::class, $route = $collection->current());
        $this->assertEquals([
            'handler' => null,
            'methods' => Route::DEFAULT_METHODS,
            'schemes' => [],
            'hosts' => [],
            'name' => null,
            'path' => '/2',
            'patterns' => [],
            'arguments' => [],
            'defaults' => [],
        ], Fixtures\Helper::routesToArray([$route], true));

        $collection->next();
        $this->assertInstanceOf(Route::class, $route = $collection->current());
        $this->assertEquals([
            'handler' => null,
            'methods' => Route::DEFAULT_METHODS,
            'schemes' => [],
            'hosts' => [],
            'name' => null,
            'path' => '/3',
            'patterns' => [],
            'arguments' => [],
            'defaults' => [],
        ], Fixtures\Helper::routesToArray([$route], true));

        $this->assertCount(3, $collection);
    }

    public function testAddRoute(): void
    {
        $collection = new RouteCollection();
        $collection->addRoute('/foo', [Router::METHOD_GET])->bind('foo');
        $collection->addRoute('/bar', [Router::METHOD_GET]);
        $collection = $this->getIterable($collection);

        $this->assertInstanceOf(Route::class, $collection->current());
        $this->assertCount(2, $collection);

        $collection->next();
        $this->assertInstanceOf(Route::class, $route = $collection->current());
        $this->assertEquals([
            'handler' => null,
            'methods' => [Router::METHOD_GET],
            'schemes' => [],
            'hosts' => [],
            'name' => 'foo',
            'path' => '/foo',
            'patterns' => [],
            'arguments' => [],
            'defaults' => [],
        ], Fixtures\Helper::routesToArray([$route], true));
    }

    public function testCannotOverriddenRoute(): void
    {
        $collection = new RouteCollection();
        $collection->add(new Route('/foo', Router::METHOD_GET));
        $collection->add(new Route('/foo1', Router::METHOD_GET));
        $collection->group('not_same', clone $collection);
        $collection = $this->getIterable($collection);

        $this->assertInstanceOf(Route::class, $route = $collection->current());
        $this->assertNull($route->getName());

        $collection->next();
        $this->assertInstanceOf(Route::class, $route = $collection->current());
        $this->assertEquals('not_sameGET_foo', $route->getName());

        $collection->next();
        $this->assertInstanceOf(Route::class, $route = $collection->current());
        $this->assertNull($route->getName());

        $collection->next();
        $this->assertInstanceOf(Route::class, $route = $collection->current());
        $this->assertEquals('not_sameGET_foo1', $route->getName());

        $this->assertCount(4, $collection);
    }

    public function testRoutesSerialization(): void
    {
        $collection = new RouteCollection();

        for ($i = 0; $i < 100; ++$i) {
            $h = \substr(\md5((string) $i), 0, 6);
            $collection->get('/' . $h . '/{a}/{b}/{c}/' . $h)->bind('_' . $i);
        }

        $serialized = \serialize($collection);

        $this->assertCount(100, $collection->getRoutes());
        $this->assertCount(100, ($collection = \unserialize($serialized))->getRoutes());
    }

    /**
     * @dataProvider populationProvider
     */
    public function testDeepOverriddenRoute(bool $c1, bool $c2, array $expected): void
    {
        $collection = new RouteCollection();
        $collection->add(new Route('/foo', Router::METHOD_GET));

        $collection1 = new RouteCollection();
        $collection1->add(new Route('/foo', Router::METHOD_GET));

        $collection2 = new RouteCollection();
        $collection2->add(new Route('foo', Router::METHOD_GET));

        $collection1->populate($collection2, $c2);
        $collection->populate($collection1, $c1);

        $this->assertCount(3, $routes = $collection->getRoutes());
        $this->assertEquals(
            $expected,
            \array_map(
                static function (Route $route): ?string {
                    return $route->getName();
                },
                \iterator_to_array($routes)
            )
        );
    }

    public function testUniqueGeneratedRouteNamesAmongMounts(): void
    {
        $controllers = new RouteCollection();

        $controllers->group('', $rootA = new RouteCollection());
        $controllers->group('', $rootB = new RouteCollection());

        $rootA->addRoute('/leaf-a', []);
        $rootB->addRoute('/leaf_a', []);

        $this->assertCount(2, $routes = $controllers->getRoutes());
        $this->assertEquals(['_leaf_a_1', '_leaf_a'], \array_map(
            static function (Route $route): string {
                return $route->getName();
            },
            \iterator_to_array($routes)
        ));
    }

    public function testLockedGroupCollection(): void
    {
        $collector = new RouteCollection();
        $collector->getRoutes();

        $this->expectExceptionObject(new \RuntimeException('Grouping index invalid or out of range, add group before calling the getRoutes() method.'));
        $collector->group('');
    }

    public function testPopulatingAsGroupCollection(): void
    {
        $collector = new RouteCollection();
        $this->assertCount(0, $collector->getRoutes());

        $collection = new RouteCollection();
        $collection->add(new Route('/foo', Router::METHOD_GET));

        $this->expectExceptionObject(new \RuntimeException('Populating a route collection as group must be done before calling the getRoutes() method.'));
        $collector->populate($collection, true);
    }

    public function testEmptyPrototype(): void
    {
        $collector = new RouteCollection();
        $collector->prototype()
            ->prefix('')
        ->end();
        $collector->get('/foo');

        $this->assertEquals('/foo', $collector->getRoutes()[0]->getPath());

        $this->expectExceptionObject(new \RuntimeException('Routes method prototyping must be done before calling the getRoutes() method.'));
        $collector->prototype();
    }

    public function testRequestMethodAsCollectionMethod(): void
    {
        $collector = new RouteCollection();
        $collector->get('/get');
        $collector->post('/post');
        $collector->put('/put');
        $collector->patch('/patch');
        $collector->delete('/delete');
        $collector->options('/options');
        $collector->any('/any');
        $collector->resource('/resource', Fixtures\BlankRestful::class, 'user');

        $routes = $this->getIterable($collector);
        $routes->uasort(function (Route $a, Route $b): int {
            return \strcmp($a->getPath(), $b->getPath());
        });

        $this->assertEquals([
            [
                'name' => null,
                'path' => '/any',
                'hosts' => [],
                'methods' => Router::HTTP_METHODS_STANDARD,
                'handler' => null,
                'schemes' => [],
                'defaults' => [],
                'patterns' => [],
                'arguments' => [],
            ],
            [
                'name' => null,
                'path' => '/delete',
                'hosts' => [],
                'methods' => [Router::METHOD_DELETE],
                'handler' => null,
                'schemes' => [],
                'defaults' => [],
                'patterns' => [],
                'arguments' => [],
            ],
            [
                'name' => null,
                'path' => '/get',
                'hosts' => [],
                'methods' => Route::DEFAULT_METHODS,
                'handler' => null,
                'schemes' => [],
                'defaults' => [],
                'patterns' => [],
                'arguments' => [],
            ],
            [
                'name' => null,
                'path' => '/options',
                'hosts' => [],
                'methods' => [Router::METHOD_OPTIONS],
                'handler' => null,
                'schemes' => [],
                'defaults' => [],
                'patterns' => [],
                'arguments' => [],
            ],
            [
                'name' => null,
                'path' => '/patch',
                'hosts' => [],
                'methods' => [Router::METHOD_PATCH],
                'handler' => null,
                'schemes' => [],
                'defaults' => [],
                'patterns' => [],
                'arguments' => [],
            ],
            [
                'name' => null,
                'path' => '/post',
                'hosts' => [],
                'methods' => [Router::METHOD_POST],
                'handler' => null,
                'schemes' => [],
                'defaults' => [],
                'patterns' => [],
                'arguments' => [],
            ],
            [
                'name' => null,
                'path' => '/put',
                'hosts' => [],
                'methods' => [Router::METHOD_PUT],
                'handler' => null,
                'schemes' => [],
                'defaults' => [],
                'patterns' => [],
                'arguments' => [],
            ],
            [
                'name' => null,
                'path' => '/resource',
                'hosts' => [],
                'methods' => Router::HTTP_METHODS_STANDARD,
                'handler' => ResourceHandler::class,
                'schemes' => [],
                'defaults' => [],
                'patterns' => [],
                'arguments' => [],
            ],
        ], Fixtures\Helper::routesToArray($routes));
    }

    public function testGroupWithInvalidController(): void
    {
        $this->expectException(\TypeError::class);

        $collector = new RouteCollection();
        $collector->group('invalid', new Fixtures\BlankController());
    }

    public function testEmptyGroupPrototype(): void
    {
        $collector = new RouteCollection();
        $collector->prototype()
            ->prefix('/foo')
        ->end()
        ->prefix('/bar');

        $collector->get('/', Fixtures\BlankRequestHandler::class)->bind('home');

        $this->assertEquals([
            'name' => 'home',
            'path' => '/',
            'hosts' => [],
            'methods' => [Router::METHOD_GET, Router::METHOD_HEAD],
            'handler' => Fixtures\BlankRequestHandler::class,
            'schemes' => [],
            'defaults' => [],
            'patterns' => [],
            'arguments' => [],
        ], Fixtures\Helper::routesToArray($collector->getRoutes(), true));
    }

    public function testDeepGrouping(): void
    {
        $collector = new RouteCollection();
        $collector->get('/', new Fixtures\BlankRequestHandler())->bind('home');

        $collector->group('api.')
            ->prototype()
                ->prefix('/api')
                ->get('/', new Fixtures\BlankRequestHandler())->bind('home')->end()
                ->get('/ping', new Fixtures\BlankRequestHandler())->bind('ping')->end()
                ->group('')
                    ->prototype()
                        ->scheme('https', 'http')->method(Router::METHOD_CONNECT)->default('hello', 'world')
                        ->head('hello', new Fixtures\BlankRequestHandler())->bind('hello')->argument('foo', 'hello')->end()
                    ->end()
                    ->method(Router::METHOD_OPTIONS)->piped('web')
                ->end()
                ->group('')
                    ->prototype()
                        ->prefix('/v1')->domain('https://youtube.com')
                        ->group('')
                            ->prototype()->prefix('/section')
                                ->post('/create', new Fixtures\BlankRequestHandler())->bind('section.create')->end()
                                ->patch('/update/{id}', new Fixtures\BlankRequestHandler())->bind('section.update')->end()
                            ->end()
                        ->end()
                    ->end()
                    ->group('')
                        ->prototype()
                            ->prefix('/product')
                            ->post('/create', new Fixtures\BlankRequestHandler())->bind('product.create')->end()
                            ->patch('/update/{id}', new Fixtures\BlankRequestHandler())->bind('product.update')->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ->end()
            ->get('/about-us', new Fixtures\BlankRequestHandler())->bind('about-us')->end();

        $this->assertCount(9, $routes = $this->getIterable($collector));
        $routes->uasort(static function (Route $a, Route $b): int {
            return \strcmp($a->getName(), $b->getName());
        });

        $this->assertEquals(['web'], $routes[1]->getPiped());
        $routes = Fixtures\Helper::routesToArray($routes);

        $this->assertEquals([
            'name' => 'home',
            'path' => '/',
            'hosts' => [],
            'methods' => [Router::METHOD_GET, Router::METHOD_HEAD],
            'handler' => Fixtures\BlankRequestHandler::class,
            'schemes' => [],
            'defaults' => [],
            'patterns' => [],
            'arguments' => [],
        ], $routes[8]);

        $this->assertEquals([
            'name' => 'api.home',
            'path' => '/api/',
            'hosts' => [],
            'methods' => [Router::METHOD_GET, Router::METHOD_HEAD],
            'handler' => Fixtures\BlankRequestHandler::class,
            'schemes' => [],
            'defaults' => [],
            'patterns' => [],
            'arguments' => [],
        ], $routes[2]);

        $this->assertEquals([
            'name' => 'api.ping',
            'path' => '/api/ping',
            'hosts' => [],
            'methods' => [Router::METHOD_GET, Router::METHOD_HEAD],
            'handler' => Fixtures\BlankRequestHandler::class,
            'schemes' => [],
            'defaults' => [],
            'patterns' => [],
            'arguments' => [],
        ], $routes[3]);

        $this->assertEquals([
            'name' => 'api.hello',
            'path' => '/api/hello',
            'hosts' => [],
            'methods' => [Router::METHOD_HEAD, Router::METHOD_CONNECT, Router::METHOD_OPTIONS],
            'handler' => Fixtures\BlankRequestHandler::class,
            'schemes' => ['https', 'http'],
            'defaults' => ['hello' => 'world'],
            'patterns' => [],
            'arguments' => ['foo' => 'hello'],
        ], $routes[1]);

        $this->assertEquals([
            'name' => 'api.section.create',
            'path' => '/api/v1/section/create',
            'hosts' => ['youtube.com'],
            'methods' => [Router::METHOD_POST],
            'handler' => Fixtures\BlankRequestHandler::class,
            'schemes' => ['https'],
            'defaults' => [],
            'patterns' => [],
            'arguments' => [],
        ], $routes[6]);

        $this->assertEquals([
            'name' => 'api.section.update',
            'path' => '/api/v1/section/update/{id}',
            'hosts' => ['youtube.com'],
            'methods' => [Router::METHOD_PATCH],
            'handler' => Fixtures\BlankRequestHandler::class,
            'schemes' => ['https'],
            'defaults' => [],
            'patterns' => [],
            'arguments' => [],
        ], $routes[7]);

        $this->assertEquals([
            'name' => 'api.product.create',
            'path' => '/api/v1/product/create',
            'hosts' => ['youtube.com'],
            'methods' => [Router::METHOD_POST],
            'handler' => Fixtures\BlankRequestHandler::class,
            'schemes' => ['https'],
            'defaults' => [],
            'patterns' => [],
            'arguments' => [],
        ], $routes[4]);

        $this->assertEquals([
            'name' => 'api.product.update',
            'path' => '/api/v1/product/update/{id}',
            'hosts' => ['youtube.com'],
            'methods' => [Router::METHOD_PATCH],
            'handler' => Fixtures\BlankRequestHandler::class,
            'schemes' => ['https'],
            'defaults' => [],
            'patterns' => [],
            'arguments' => [],
        ], $routes[5]);

        $this->assertEquals([
            'name' => 'about-us',
            'path' => '/about-us',
            'hosts' => [],
            'methods' => [Router::METHOD_GET, Router::METHOD_HEAD],
            'handler' => Fixtures\BlankRequestHandler::class,
            'schemes' => [],
            'defaults' => [],
            'patterns' => [],
            'arguments' => [],
        ], $routes[0]);
    }

    /**
     * @dataProvider provideCollectionData
     */
    public function testCollectionGroupingAndWithCache(bool $cached): void
    {
        $router = new Router(null, $cached ? self::$cacheFile : null);

        $router->setCollection(static function (RouteCollection $mergedCollection): void {
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
            $demoCollection->add(new Route('/', Router::METHOD_GET));
            $demoCollection->prefix('/{_locale}');
            $demoCollection->method(Router::METHOD_CONNECT);
            $mergedCollection->group('demo.', $demoCollection)->default('_locale', 'en')->assert('_locale', 'en|fr');

            $chunkedCollection = new RouteCollection();
            $chunkedCollection->prototype()
                ->domain('http://localhost')
                ->scheme('https', 'http')
            ->end();

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
            $groupOptimisedCollection->addRoute('/nested/{param}', [Router::METHOD_GET])->bind('nested_wildcard');
            $groupOptimisedCollection->addRoute('/nested/group/a/', [Router::METHOD_GET])->bind('nested_a');
            $groupOptimisedCollection->addRoute('/nested/group/b/', [Router::METHOD_GET])->bind('nested_b');
            $groupOptimisedCollection->addRoute('/nested/group/c/', [Router::METHOD_GET])->bind('nested_c');
            $groupOptimisedCollection->addRoute('a_sixth', [Router::METHOD_GET], '/a/66/', Fixtures\BlankController::class);

            $groupOptimisedCollection->addRoute('/slashed/group/', [Router::METHOD_GET])->bind('slashed_a');
            $groupOptimisedCollection->addRoute('/slashed/group/b/', [Router::METHOD_GET])->bind('slashed_b');
            $groupOptimisedCollection->addRoute('/slashed/group/c/', [Router::METHOD_GET])->bind('slashed_c');

            $mergedCollection->group('', $groupOptimisedCollection);
        });

        $this->assertCount(128, $routes = iterator_to_array($router->getMatcher()->getRoutes()));
        \uasort($routes, static function (Route $a, Route $b): int {
            return \strcmp($a->getName(), $b->getName());
        });

        $this->assertEquals([
            0 => 'GET_a_sixth',
            1 => 'a_fifth',
            2 => 'a_first',
            3 => 'a_fourth',
            4 => 'a_second',
            5 => 'a_third',
            6 => 'a_wildcard',
            7 => 'chuck__0',
            8 => 'chuck__1',
            9 => 'chuck__10',
            10 => 'chuck__11',
            11 => 'chuck__12',
            12 => 'chuck__13',
            13 => 'chuck__14',
            14 => 'chuck__15',
            15 => 'chuck__16',
            16 => 'chuck__17',
            17 => 'chuck__18',
            18 => 'chuck__19',
            19 => 'chuck__2',
            20 => 'chuck__20',
            21 => 'chuck__21',
            22 => 'chuck__22',
            23 => 'chuck__23',
            24 => 'chuck__24',
            25 => 'chuck__25',
            26 => 'chuck__26',
            27 => 'chuck__27',
            28 => 'chuck__28',
            29 => 'chuck__29',
            30 => 'chuck__3',
            31 => 'chuck__30',
            32 => 'chuck__31',
            33 => 'chuck__32',
            34 => 'chuck__33',
            35 => 'chuck__34',
            36 => 'chuck__35',
            37 => 'chuck__36',
            38 => 'chuck__37',
            39 => 'chuck__38',
            40 => 'chuck__39',
            41 => 'chuck__4',
            42 => 'chuck__40',
            43 => 'chuck__41',
            44 => 'chuck__42',
            45 => 'chuck__43',
            46 => 'chuck__44',
            47 => 'chuck__45',
            48 => 'chuck__46',
            49 => 'chuck__47',
            50 => 'chuck__48',
            51 => 'chuck__49',
            52 => 'chuck__5',
            53 => 'chuck__50',
            54 => 'chuck__51',
            55 => 'chuck__52',
            56 => 'chuck__53',
            57 => 'chuck__54',
            58 => 'chuck__55',
            59 => 'chuck__56',
            60 => 'chuck__57',
            61 => 'chuck__58',
            62 => 'chuck__59',
            63 => 'chuck__6',
            64 => 'chuck__60',
            65 => 'chuck__61',
            66 => 'chuck__62',
            67 => 'chuck__63',
            68 => 'chuck__64',
            69 => 'chuck__65',
            70 => 'chuck__66',
            71 => 'chuck__67',
            72 => 'chuck__68',
            73 => 'chuck__69',
            74 => 'chuck__7',
            75 => 'chuck__70',
            76 => 'chuck__71',
            77 => 'chuck__72',
            78 => 'chuck__73',
            79 => 'chuck__74',
            80 => 'chuck__75',
            81 => 'chuck__76',
            82 => 'chuck__77',
            83 => 'chuck__78',
            84 => 'chuck__79',
            85 => 'chuck__8',
            86 => 'chuck__80',
            87 => 'chuck__81',
            88 => 'chuck__82',
            89 => 'chuck__83',
            90 => 'chuck__84',
            91 => 'chuck__85',
            92 => 'chuck__86',
            93 => 'chuck__87',
            94 => 'chuck__88',
            95 => 'chuck__89',
            96 => 'chuck__9',
            97 => 'chuck__90',
            98 => 'chuck__91',
            99 => 'chuck__92',
            100 => 'chuck__93',
            101 => 'chuck__94',
            102 => 'chuck__95',
            103 => 'chuck__96',
            104 => 'chuck__97',
            105 => 'chuck__98',
            106 => 'chuck__99',
            107 => 'demo.DELETE_CONNECT_locale_admin_post_id_delete',
            108 => 'demo.GET_CONNECT_locale_',
            109 => 'demo.GET_CONNECT_locale_blog_',
            110 => 'demo.GET_CONNECT_locale_blog_comments_id_new',
            111 => 'demo.GET_CONNECT_locale_blog_page_page',
            112 => 'demo.GET_CONNECT_locale_blog_posts_page',
            113 => 'demo.GET_CONNECT_locale_blog_rss.xml',
            114 => 'demo.GET_CONNECT_locale_blog_search',
            115 => 'demo.PATCH_CONNECT_locale_admin_post_id_edit',
            116 => 'demo.POST_CONNECT_locale_admin_post_',
            117 => 'demo.POST_CONNECT_locale_admin_post_id',
            118 => 'demo.POST_CONNECT_locale_admin_post_new',
            119 => 'demo.POST_CONNECT_locale_login',
            120 => 'demo.POST_CONNECT_locale_logout',
            121 => 'nested_a',
            122 => 'nested_b',
            123 => 'nested_c',
            124 => 'nested_wildcard',
            125 => 'slashed_a',
            126 => 'slashed_b',
            127 => 'slashed_c',
        ], Fixtures\Helper::routesToNames($routes));

        $route = $router->matchRequest(new ServerRequest(Router::METHOD_GET, '/fr/blog'));

        $this->assertEquals([
            'name' => 'demo.GET_CONNECT_locale_blog_',
            'path' => '/{_locale}/blog/',
            'hosts' => [],
            'methods' => [Router::METHOD_GET, Router::METHOD_CONNECT],
            'handler' => null,
            'schemes' => [],
            'defaults' => ['_locale' => 'en'],
            'arguments' => ['_locale' => 'fr'],
            'patterns' => ['_locale' => 'en|fr'],
        ], Fixtures\Helper::routesToArray([$route], true));

        $this->assertEquals($cached, $router->isCached());
        $this->assertEquals('./hello', (string) $router->generateUri('a_wildcard', ['param' => 'hello']));
        $this->assertInstanceOf(RouteCompiler::class, $router->getMatcher()->getCompiler());
    }

    /**
     * @return array<int,array<int,bool>>
     */
    public function provideCollectionData(): array
    {
        return [[false], [true], [true]];
    }

    /**
     * @return array>int,array<int,mixed>>
     */
    public function populationProvider(): array
    {
        // [collection1, collection2, expect]
        return [
            [true, true, [null, 'GET_foo', 'GET_foo']],
            [false, true, [null, null, 'GET_foo']],
            [true, false, [null, 'GET_foo', 'GET_foo_1']],
            [false, false, [null, null, null]],
        ];
    }

    /**
     * Return Collections Routes as iterator.
     *
     * @return \ArrayIterator<Route>
     */
    private function getIterable(RouteCollection $collection): \ArrayIterator
    {
        $routes = $collection->getRoutes();
        $this->assertInstanceOf(\SplFixedArray::class, $routes);

        return new \ArrayIterator(\iterator_to_array($routes));
    }
}
