<?php declare(strict_types=1);

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

use Biurad\Annotations\AnnotationLoader;
use Biurad\Annotations\InvalidAnnotationException;
use Flight\Routing\Annotation\Listener;
use Flight\Routing\Annotation\Route;
use Flight\Routing\Exceptions\InvalidControllerException;
use Flight\Routing\Exceptions\UriHandlerException;
use Flight\Routing\Handlers\ResourceHandler;
use Flight\Routing\RouteCollection;
use PHPUnit\Framework as t;
use Spiral\Attributes\AnnotationReader;
use Spiral\Attributes\AttributeReader;
use Spiral\Attributes\Composite\MergeReader;

test('if the route collection is empty', function (): void {
    $collection = new RouteCollection();
    $collection->group('empty');
    t\assertCount(0, $collection);
});

test('if the route collection is not empty', function (): void {
    $collection = new RouteCollection();
    $collection->populate(new RouteCollection());
    $collection->get('/', fn (): string => 'Hello World');
    t\assertCount(1, $collection);
});

test('if route collection common methods works', function (): void {
    $collection = new RouteCollection();
    $collection->get('/a', fn (): string => 'Hello World');
    $collection->post('/b', fn (): string => 'Hello World');
    $collection->put('/c', fn (): string => 'Hello World');
    $collection->patch('/d', fn (): string => 'Hello World');
    $collection->delete('/e', fn (): string => 'Hello World');
    $collection->options('/f', fn (): string => 'Hello World');
    $collection->any('/g', fn (): string => 'Hello World');
    $collection->resource('/h', 'HelloHandler');
    $collection->add('/i', handler: fn (): string => 'Hello World');
    $collection->group('a', function (RouteCollection $collection): void {
        $collection->get('/j', fn (): string => 'Hello World');
    });

    t\assertCount(10, $collection);
    t\assertEquals(<<<'EOT'
    [
        [
            'handler' => fn() => 'Hello World',
            'prefix' => '/a',
            'path' => '/a',
            'methods' => [
                'GET' => true,
                'HEAD' => true,
            ],
        ],
        [
            'handler' => fn() => 'Hello World',
            'prefix' => '/b',
            'path' => '/b',
            'methods' => [
                'POST' => true,
            ],
        ],
        [
            'handler' => fn() => 'Hello World',
            'prefix' => '/c',
            'path' => '/c',
            'methods' => [
                'PUT' => true,
            ],
        ],
        [
            'handler' => fn() => 'Hello World',
            'prefix' => '/d',
            'path' => '/d',
            'methods' => [
                'PATCH' => true,
            ],
        ],
        [
            'handler' => fn() => 'Hello World',
            'prefix' => '/e',
            'path' => '/e',
            'methods' => [
                'DELETE' => true,
            ],
        ],
        [
            'handler' => fn() => 'Hello World',
            'prefix' => '/f',
            'path' => '/f',
            'methods' => [
                'OPTIONS' => true,
            ],
        ],
        [
            'handler' => fn() => 'Hello World',
            'prefix' => '/g',
            'path' => '/g',
            'methods' => [
                'HEAD' => true,
                'GET' => true,
                'POST' => true,
                'PUT' => true,
                'PATCH' => true,
                'DELETE' => true,
                'PURGE' => true,
                'OPTIONS' => true,
                'TRACE' => true,
                'CONNECT' => true,
            ],
        ],
        [
            'handler' => new ResourceHandler([
                'HelloHandler',
                'Action',
            ]),
            'prefix' => '/h',
            'path' => '/h',
            'methods' => [
                'HEAD' => true,
                'GET' => true,
                'POST' => true,
                'PUT' => true,
                'PATCH' => true,
                'DELETE' => true,
                'PURGE' => true,
                'OPTIONS' => true,
                'TRACE' => true,
                'CONNECT' => true,
            ],
        ],
        [
            'handler' => fn() => 'Hello World',
            'prefix' => '/i',
            'path' => '/i',
            'methods' => [
                'GET' => true,
                'HEAD' => true,
            ],
        ],
        [
            'handler' => fn() => 'Hello World',
            'prefix' => '/j',
            'path' => '/j',
            'methods' => [
                'GET' => true,
                'HEAD' => true,
            ],
            'name' => 'aGET_HEAD_j',
        ],
    ]
    EOT, debugFormat($collection->getRoutes()));
});

test('if route collection routes can be accessed using the [] operator', function (): void {
    $collection = new RouteCollection();
    $collection->add('hello', ['GET']);
    $collection->add('hello1', ['GET']);

    t\assertTrue(isset($collection[1]));
    t\assertSame('/hello1', $collection[1]['path']);

    unset($collection[1]);
    t\assertNull($collection[1] ?? null);
    t\assertSame('/hello', $collection[0]['path']);

    $collection[2] = ['path' => '/hello2'];
})->throws(
    \BadMethodCallException::class,
    'The operator "[]" for new route, use the add() method instead.'
);

test('if route collection routes are sorted', function (): void {
    $collection = new RouteCollection();
    $collection->get('/a1');
    $collection->get('/c4');
    $collection->get('/d7');
    $collection->get('/c5');
    $collection->get('/b3');
    $collection->get('/b2');
    $collection->get('/{foo}');
    $collection->get('/c6');
    $collection->get('/f9');
    $collection->get('/e8');
    $collection->get('/foo/{bar}');
    $collection->sort();

    t\assertSame([
        '/a1',
        '/b2',
        '/b3',
        '/c4',
        '/c5',
        '/c6',
        '/d7',
        '/e8',
        '/f9',
        '/foo/{bar}',
        '/{foo}',
    ], \array_map(fn (array $v) => $v['path'], $collection->getRoutes()));
});

test('if route collection route prototyping works', function (): void {
    $collection = new RouteCollection();
    $collection->add('world');
    $collection->method('CONNECT', 'PATCH');
    $collection->scheme('http');
    $collection->domain('https://example.com');
    $collection->bind('greet');
    $collection->run('HelloWorld');
    $collection->namespace('Demo\\');
    $collection->arguments(['hi' => 'Divine', 'code' => '233']);
    $collection->defaults(['follow' => 'Me']);
    $collection->placeholders(['number' => '\d+']);
    $collection->piped('web');
    $collection->prefix('/hello');
    $collection->set('flight', 'routing');
    $collection->prototype(true);
    $collection->set('data', ['hello', 'world']);

    t\assertEquals(<<<'EOT'
    [
        'handler' => 'Demo\\HelloWorld',
        'prefix' => '/hello/world',
        'path' => '/hello/world',
        'methods' => [
            'GET' => true,
            'HEAD' => true,
            'CONNECT' => true,
            'PATCH' => true,
        ],
        'schemes' => [
            'http' => true,
            'https' => true,
        ],
        'hosts' => [
            'example.com' => true,
        ],
        'name' => 'greet',
        'arguments' => [
            'hi' => 'Divine',
            'code' => 233,
        ],
        'defaults' => [
            'follow' => 'Me',
        ],
        'placeholders' => [
            'number' => '\\d+',
        ],
        'middlewares' => [
            'web' => true,
        ],
        'flight' => 'routing',
        'data' => [
            'hello',
            'world',
        ],
    ]
    EOT, debugFormat(\current($collection->getRoutes())));
});

test('if route namespace on handler can be resolvable', function (): void {
    $collection = new RouteCollection();
    $collection->add('/a', handler: 'HelloWorld')->namespace('Demo\\');
    $collection->add('/b')->namespace('Demo\\')->run('\\HelloWorld');
    $collection->add('/c', handler: ['HelloWorld', 'run'])->namespace('Demo\\');
    $collection->add('/d', handler: ['\\HelloWorld', 'run'])->namespace('Demo\\');
    $collection->add('/e', handler: new ResourceHandler('\\BlankRestful'))->namespace('Demo\\');
    $collection->add('/f', handler: new ResourceHandler('BlankRestful'))->namespace('Demo\\');

    $routes = $collection->getRoutes();
    t\assertSame('Demo\\HelloWorld', $routes[0]['handler']);
    t\assertSame('\\HelloWorld', $routes[1]['handler']);
    t\assertSame(['Demo\\HelloWorld', 'run'], $routes[2]['handler']);
    t\assertSame(['\\HelloWorld', 'run'], $routes[3]['handler']);
    t\assertSame(['BlankRestful', 'Action'], $routes[4]['handler'](''));
    t\assertSame(['Demo\\BlankRestful', 'Action'], $routes[5]['handler'](''));
});

test('if an array like route handler can be namespaced', function (): void {
    $collection = new RouteCollection();
    $collection->add('/g', handler: ['1', 2, '3'])->namespace('Demo\\');
    $this->fail('Expected an exception to be thrown as route handler is invalid');
})->throws(
    InvalidControllerException::class,
    'Cannot use a non callable like array as route handler.'
);

test('if route handler namespace ending slash can be omitted', function (): void {
    $collection = new RouteCollection();
    $collection->add('/g', handler: 'HelloWorld')->namespace('Demo');
    $this->fail('Expected an exception to be thrown as route handler\'s namespace is invalid');
})->throws(
    InvalidControllerException::class,
    'Cannot set a route\'s handler namespace "Demo" without an ending "\".'
);

test('if certain methods in the route collection class fails when route not set', function (): void {
    $collection = new RouteCollection();

    try {
        $collection->bind('hello');
        $this->fail('Expected to throw an exception as a name cannot be set to an empty route');
    } catch (\InvalidArgumentException $e) {
        t\assertEquals('Cannot use the "bind()" method if route not defined.', $e->getMessage());
    }

    try {
        $collection->path('hello');
        $this->fail('Expected to throw an exception as a path cannot be set to an empty route');
    } catch (\InvalidArgumentException $e) {
        t\assertEquals('Cannot use the "path()" method if route not defined.', $e->getMessage());
    }

    try {
        $collection->prototype(['run' => 'phpinfo']);
        $this->fail('Expected to throw an exception as a handler cannot be set to an empty route');
    } catch (\InvalidArgumentException $e) {
        t\assertEquals('Cannot use the "run()" method if route not defined.', $e->getMessage());
    }
});

test('if route path is not valid', function (): void {
    $collection = new RouteCollection();
    $collection->add('//localhost');
    $this->fail('Expected to throw an exception as route path is not valid');
})->throws(
    UriHandlerException::class,
    'The route pattern "//localhost" is invalid as route path must be present in pattern.'
);

test('if route path can resolve all accepted constraints', function (): void {
    $collection = new RouteCollection();
    $collection->add('https://example.com/a/{b}*<HelloWorld@handle>');
    $collection->add('//example.com/c/{d}*<handle>', handler: 'HelloWorld');
    $collection->add('/e/{f}*<phpinfo>');

    t\assertEquals(<<<'EOT'
    [
        [
            'handler' => [
                'HelloWorld',
                'handle',
            ],
            'schemes' => [
                'https' => true,
            ],
            'hosts' => [
                'example.com' => true,
            ],
            'prefix' => '/a',
            'path' => '/a/{b}',
            'methods' => [
                'GET' => true,
                'HEAD' => true,
            ],
        ],
        [
            'handler' => [
                'HelloWorld',
                'handle',
            ],
            'hosts' => [
                'example.com' => true,
            ],
            'prefix' => '/c',
            'path' => '/c/{d}',
            'methods' => [
                'GET' => true,
                'HEAD' => true,
            ],
        ],
        [
            'handler' => 'phpinfo',
            'prefix' => '/e',
            'path' => '/e/{f}',
            'methods' => [
                'GET' => true,
                'HEAD' => true,
            ],
        ],
    ]
    EOT, debugFormat($collection->getRoutes()));
});

test('if route path can be prefixed', function (array|string $prefixes, string $path, string|array $expected): void {
    $collection = new RouteCollection();
    $collection->add($path);

    if (!\is_array($expected)) {
        $expected = [$expected];
    }

    foreach ((array) $prefixes as $i => $prefix) {
        $collection->prefix($prefix);
        t\assertSame($expected[$i], \current($collection->getRoutes())['path']);
    }
})->with([
    ['', '/bar', '/bar'],
    ['/foo', '/bar', '/foo/bar'],
    [['/c', '/b', '/a'], '/hello', ['/c/hello', '/b/c/hello', '/a/b/c/hello']],
    [['/c.', 'b.', '/a.'], '/hello', ['/c.hello', '/b.c.hello', '/a.b.c.hello']],
    ['/foo/', '/bar', '/foo/bar'],
    ['/bar~', '/foo', '/bar~foo'],
]);

test('if route internal data name can be overridden by the set method', function (): void {
    $collection = new RouteCollection();
    $collection->set('name', 'Divine');
})->throws(
    \InvalidArgumentException::class,
    'Cannot replace the default "name" route binding.'
);

test('if route collection can deep override route', function (bool $c1, bool $c2, array $expected): void {
    $collection = new RouteCollection();
    $collection->add('/foo', ['GET']);

    $collection1 = new RouteCollection();
    $collection1->add('/foo', ['GET']);

    $collection2 = new RouteCollection();
    $collection2->add('foo', ['GET']);

    $collection1->populate($collection2, $c2);
    $collection->populate($collection1, $c1);

    t\assertCount(3, $routes = $collection->getRoutes());
    t\assertEquals($expected, \array_map(fn (array $route) => $route['name'] ?? null, $routes));
})->with([
    [true, true, [null, 'GET_foo', 'GET_foo']],
    [false, true, [null, 2 => null, 3 => 'GET_foo']],
    [true, false, [null, 'GET_foo', 'GET_foo_1']],
    [false, false, [null, null, null]],
]);

test('if unnamed routes can in a nested group can be named', function (): void {
    $controllers = new RouteCollection();
    $controllers->group('', $rootA = new RouteCollection());
    $controllers->group('', $rootB = new RouteCollection());

    $rootA->add('/leaf-a', []);
    $rootB->add('/leaf_a', []);
    $rootA->add('/leaf_a', ['GET']);
    $rootB->add('/leaf_a', ['GET']);

    $this->assertCount(4, $routes = $controllers->getRoutes());
    $this->assertEquals(
        ['_leaf_a', 'GET_leaf_a', '_leaf_a_1', 'GET_leaf_a_1'],
        \array_map(fn (array $route) => $route['name'] ?? null, $routes)
    );
});

test('if route collections routes and groups can be prototyped', function (): void {
    $collection = new RouteCollection();
    $collection->add('/hello')->prototype([
        'bind' => 'greeting',
        'method' => 'OPTIONS',
        'scheme' => ['http'],
    ]);

    $group = $collection->group(return: true)->prototype([
        'domain' => 'biurad.com',
        'method' => ['OPTIONS'],
    ]);
    $group->add('/foo', ['GET']);
    $group->end();

    t\assertEquals(<<<'EOT'
    [
        [
            'handler' => null,
            'prefix' => '/hello',
            'path' => '/hello',
            'methods' => [
                'GET' => true,
                'HEAD' => true,
                'OPTIONS' => true,
            ],
            'name' => 'greeting',
            'schemes' => [
                'http' => true,
            ],
        ],
        [
            'handler' => null,
            'prefix' => '/foo',
            'path' => '/foo',
            'hosts' => [
                'biurad.com' => true,
            ],
            'methods' => [
                'OPTIONS' => true,
                'GET' => true,
            ],
            'name' => 'OPTIONS_GET_foo',
        ],
    ]
    EOT, debugFormat($collection->getRoutes()));
});

test('if simple route grouping is possible', function (): void {
    $collection = new RouteCollection();
    $collection->namespace('Controller\\');
    $collection->defaults(['hello' => 'world']);
    $collection->placeholders(['locale' => 'en|fr']);
    $collection->scheme('http');
    $collection->piped('auth');
    $collection->group(return: true)
        ->argument('name', 'Divine')
        ->get('/hello/{name}', 'HelloWorld::handle')
        ->namespace('Greet\\')
        ->placeholder('name', '\w+')
    ;
    $collection->prefix('{locale}/');
    $collection->method('OPTIONS');
    $collection->namespace('App\\');
    $collection->argument('foo', 'bar');
    $collection->placeholder('bar', '\w+');
    $collection->scheme('https');
    $collection->piped('web');
    $collection->prototype(true)
        ->domain('example.com')
        ->defaults(['data' => ['hello' => 'world']])
        ->end()
    ;

    t\assertEquals(<<<'EOT'
    [
        'handler' => 'App\\Greet\\Controller\\HelloWorld::handle',
        'prefix' => null,
        'path' => '/{locale}/hello/{name}',
        'defaults' => [
            'hello' => 'world',
            'data' => [
                'hello' => 'world',
            ],
        ],
        'placeholders' => [
            'locale' => 'en|fr',
            'name' => '\\w+',
            'bar' => '\\w+',
        ],
        'schemes' => [
            'http' => true,
            'https' => true,
        ],
        'middlewares' => [
            'auth' => true,
            'web' => true,
        ],
        'arguments' => [
            'name' => 'Divine',
            'foo' => 'bar',
        ],
        'methods' => [
            'GET' => true,
            'HEAD' => true,
            'OPTIONS' => true,
        ],
        'hosts' => [
            'example.com' => true,
        ],
        'name' => 'GET_HEAD_OPTIONS_locale_hello_name',
    ]
    EOT, debugFormat(\current($collection->getRoutes())));
});

test('if deep route grouping is possible', function (): void {
    $collection = new RouteCollection();
    $collection->get('/', 'Home::index')->bind('home');

    $nested = new RouteCollection();
    $nested->get('/', 'Home::indexApi');
    $nested->get('/ping', 'Home::ping')->bind('ping');

    $collection->group('api.', return: true)
        ->prefix('/api')
        ->populate($nested)
        ->group(null, static function (RouteCollection $collection): void {
            $collection->scheme('https', 'http')
                ->method('CONNECT')
                ->set('something', 'different')
                ->get('hello', 'Home::greet')->bind('hello')->argument('foo', 'hello')->end()
                ->method('OPTIONS')->piped('web');
        })
        ->group(return: true)
        ->prototype(['prefix' => '/v1', 'domain' => 'https://products.example.com'])
        ->group(return: true)
        ->prefix('/section')
        ->post('/create', 'Home::createSection')->bind('section.create')
        ->patch('/update/{id}', 'Home::sectionUpdate')->bind('section.update')
        ->end()
        ->group(return: true)
        ->prefix('/product')
        ->post('/create', 'Home::createProduct')->bind('product.create')
        ->patch('/update/{id}', 'Home::productUpdate')->bind('product.update')
        ->end()
        ->end()
        ->get('/about-us', 'Home::aboutUs')->bind('about-us')->sort();

    t\assertCount(9, $collection);
    t\assertEquals(['web' => true], ($routes = $collection->getRoutes())[2]['middlewares']);
    t\assertEquals(<<<'EOT'
    [
        [
            'handler' => 'Home::index',
            'prefix' => null,
            'path' => '/',
            'methods' => [
                'GET' => true,
                'HEAD' => true,
            ],
            'name' => 'home',
        ],
        [
            'handler' => 'Home::aboutUs',
            'prefix' => '/api/about-us',
            'path' => '/api/about-us',
            'methods' => [
                'GET' => true,
                'HEAD' => true,
            ],
            'name' => 'api.about-us',
        ],
        [
            'handler' => 'Home::greet',
            'prefix' => '/api/hello',
            'path' => '/api/hello',
            'schemes' => [
                'https' => true,
                'http' => true,
            ],
            'methods' => [
                'CONNECT' => true,
                'GET' => true,
                'HEAD' => true,
                'OPTIONS' => true,
            ],
            'something' => 'different',
            'name' => 'api.hello',
            'arguments' => [
                'foo' => 'hello',
            ],
            'middlewares' => [
                'web' => true,
            ],
        ],
        [
            'handler' => 'Home::createProduct',
            'prefix' => '/api/v1/product/create',
            'path' => '/api/v1/product/create',
            'schemes' => [
                'https' => true,
            ],
            'hosts' => [
                'products.example.com' => true,
            ],
            'methods' => [
                'POST' => true,
            ],
            'name' => 'api.product.create',
        ],
        [
            'handler' => 'Home::createSection',
            'prefix' => '/api/v1/section/create',
            'path' => '/api/v1/section/create',
            'schemes' => [
                'https' => true,
            ],
            'hosts' => [
                'products.example.com' => true,
            ],
            'methods' => [
                'POST' => true,
            ],
            'name' => 'api.section.create',
        ],
        [
            'handler' => 'Home::indexApi',
            'prefix' => [
                '/api',
                null,
            ],
            'path' => '/api/',
            'methods' => [
                'GET' => true,
                'HEAD' => true,
            ],
            'name' => 'api.GET_HEAD_api_',
        ],
        [
            'handler' => 'Home::ping',
            'prefix' => [
                '/api/ping',
                '/ping',
            ],
            'path' => '/api/ping',
            'methods' => [
                'GET' => true,
                'HEAD' => true,
            ],
            'name' => 'api.ping',
        ],
        [
            'handler' => 'Home::productUpdate',
            'prefix' => '/api/v1/product/update',
            'path' => '/api/v1/product/update/{id}',
            'schemes' => [
                'https' => true,
            ],
            'hosts' => [
                'products.example.com' => true,
            ],
            'methods' => [
                'PATCH' => true,
            ],
            'name' => 'api.product.update',
        ],
        [
            'handler' => 'Home::sectionUpdate',
            'prefix' => '/api/v1/section/update',
            'path' => '/api/v1/section/update/{id}',
            'schemes' => [
                'https' => true,
            ],
            'hosts' => [
                'products.example.com' => true,
            ],
            'methods' => [
                'PATCH' => true,
            ],
            'name' => 'api.section.update',
        ],
    ]
    EOT, debugFormat($routes));
});

test('if attribute route is resolvable', function (): void {
    $params = [
        'name' => 'foo',
        'path' => '/foo',
        'methods' => ['GET'],
    ];
    $route = new Route($params['path'], $params['name'], $params['methods']);

    t\assertSame($params['name'], $route->name);
    t\assertSame($params['path'], $route->path);
    t\assertSame($params['methods'], $route->methods);

    // default property values...
    t\assertSame([], $route->defaults);
    t\assertSame([], $route->arguments);
    t\assertSame([], $route->where);
    t\assertSame([], $route->schemes);
    t\assertSame([], $route->hosts);
});

test('if fetching of attribute/annotation routes from directories is possible', function (): void {
    $reader = new AnnotationLoader(new MergeReader([new AnnotationReader(), new AttributeReader()]));
    $reader->listener(new Listener());
    $reader->resource(...[
        __DIR__.'/../tests/Fixtures/Annotation/Route/Valid',
        __DIR__.'/../tests/Fixtures/Annotation/Route/Containerable',
        __DIR__.'/../tests/Fixtures/Annotation/Route/Attribute',
        __DIR__.'/../tests/Fixtures/Annotation/Route/Abstracts', // Abstract should be excluded
    ]);
    t\assertCount(26, $routes = $reader->load(Listener::class));

    $collection = new RouteCollection();
    $collection->populate($routes, true);
    $names = \array_map(fn (array $v) => $v['name'] ?? null, $collection->getRoutes());
    \sort($names);
    t\assertSame([
        'GET_HEAD_get',
        'GET_HEAD_get_1',
        'GET_HEAD_testing_',
        'GET_POST_default',
        'POST_post',
        'PUT_put',
        'action',
        'attribute_GET_HEAD_defaults_localespecific_none',
        'attribute_specific_name',
        'class_group@CONNECT_GET_HEAD_get',
        'class_group@CONNECT_POST_post',
        'class_group@CONNECT_PUT_put',
        'do.action',
        'do.action_two',
        'english_locale',
        'foo',
        'french_locale',
        'hello_with_default',
        'hello_without_default',
        'home',
        'lol',
        'method_not_array',
        'ping',
        'sub-dir:bar',
        'sub-dir:foo',
        'user__restful',
    ], $names);

    $routes->sort();
    $routes = $routes->getRoutes();
    $routes[4]['handler'][0] = 'Flight\\Routing\\Tests\\Fixtures\\Annotation\\Route\\Valid\\MultipleMethodRouteController';
    $routes[5]['handler'][0] = 'Flight\\Routing\\Tests\\Fixtures\\Annotation\\Route\\Valid\\DefaultNameController';
    t\assertEquals(<<<'EOT'
    [
        [
            'handler' => [
                'Flight\\Routing\\Tests\\Fixtures\\Annotation\\Route\\Valid\\DefaultNameController',
                'default',
            ],
            'prefix' => '/default',
            'path' => '/default',
            'methods' => [
                'GET' => true,
                'POST' => true,
            ],
        ],
        [
            'handler' => [
                'Flight\\Routing\\Tests\\Fixtures\\Annotation\\Route\\Valid\\MultipleClassRouteController',
                'default',
            ],
            'prefix' => '/en/locale',
            'path' => '/en/locale',
            'methods' => [
                'GET' => true,
                'HEAD' => true,
            ],
            'name' => 'english_locale',
        ],
        [
            'handler' => 'Flight\\Routing\\Tests\\Fixtures\\Annotation\\Route\\Containerable\\FooRequestHandler',
            'prefix' => '/foo',
            'path' => '/foo',
            'methods' => [
                'GET' => true,
            ],
            'name' => 'foo',
        ],
        [
            'handler' => [
                'Flight\\Routing\\Tests\\Fixtures\\Annotation\\Route\\Valid\\MultipleClassRouteController',
                'default',
            ],
            'prefix' => '/fr/locale',
            'path' => '/fr/locale',
            'methods' => [
                'GET' => true,
                'HEAD' => true,
            ],
            'name' => 'french_locale',
        ],
        [
            'handler' => [
                'Flight\\Routing\\Tests\\Fixtures\\Annotation\\Route\\Valid\\MultipleMethodRouteController',
                'default',
            ],
            'prefix' => '/get',
            'path' => '/get',
            'methods' => [
                'GET' => true,
                'HEAD' => true,
            ],
        ],
        [
            'handler' => [
                'Flight\\Routing\\Tests\\Fixtures\\Annotation\\Route\\Valid\\DefaultNameController',
                'default',
            ],
            'prefix' => '/get',
            'path' => '/get',
            'methods' => [
                'GET' => true,
                'HEAD' => true,
            ],
        ],
        [
            'handler' => [
                'Flight\\Routing\\Tests\\Fixtures\\Annotation\\Route\\Valid\\ClassGroupWithoutPath',
                'default',
            ],
            'prefix' => '/get',
            'path' => '/get',
            'methods' => [
                'CONNECT' => true,
                'GET' => true,
                'HEAD' => true,
            ],
            'name' => 'class_group@CONNECT_GET_HEAD_get',
        ],
        [
            'handler' => 'Flight\\Routing\\Tests\\Fixtures\\Annotation\\Route\\Valid\\InvokableController',
            'prefix' => '/here',
            'path' => '/here',
            'methods' => [
                'GET' => true,
                'POST' => true,
            ],
            'name' => 'lol',
            'schemes' => [
                'https' => true,
            ],
            'arguments' => [
                'hello' => 'world',
            ],
        ],
        [
            'handler' => 'Flight\\Routing\\Tests\\Fixtures\\Annotation\\Route\\Valid\\MethodsNotArray',
            'prefix' => '/method_not_array',
            'path' => '/method_not_array',
            'methods' => [
                'GET' => true,
            ],
            'name' => 'method_not_array',
        ],
        [
            'handler' => 'Flight\\Routing\\Tests\\Fixtures\\Annotation\\Route\\Valid\\PingRequestHandler',
            'prefix' => '/ping',
            'path' => '/ping',
            'methods' => [
                'HEAD' => true,
                'GET' => true,
            ],
            'name' => 'ping',
            'defaults' => [
                'foo' => 'bar',
                'bar' => 'baz',
            ],
        ],
        [
            'handler' => [
                'Flight\\Routing\\Tests\\Fixtures\\Annotation\\Route\\Valid\\MultipleMethodRouteController',
                'default',
            ],
            'prefix' => '/post',
            'path' => '/post',
            'methods' => [
                'POST' => true,
            ],
        ],
        [
            'handler' => [
                'Flight\\Routing\\Tests\\Fixtures\\Annotation\\Route\\Valid\\ClassGroupWithoutPath',
                'default',
            ],
            'prefix' => '/post',
            'path' => '/post',
            'methods' => [
                'CONNECT' => true,
                'POST' => true,
            ],
            'name' => 'class_group@CONNECT_POST_post',
        ],
        [
            'handler' => [
                'Flight\\Routing\\Tests\\Fixtures\\Annotation\\Route\\Valid\\RouteWithPrefixController',
                'action',
            ],
            'prefix' => '/prefix/path',
            'path' => '/prefix/path',
            'hosts' => [
                'biurad.com' => true,
            ],
            'methods' => [
                'GET' => true,
                'POST' => true,
            ],
            'name' => 'do.action',
        ],
        [
            'handler' => [
                'Flight\\Routing\\Tests\\Fixtures\\Annotation\\Route\\Valid\\RouteWithPrefixController',
                'actionTwo',
            ],
            'prefix' => '/prefix/path_two',
            'path' => '/prefix/path_two',
            'hosts' => [
                'biurad.com' => true,
            ],
            'methods' => [
                'GET' => true,
                'POST' => true,
            ],
            'name' => 'do.action_two',
        ],
        [
            'handler' => [
                'Flight\\Routing\\Tests\\Fixtures\\Annotation\\Route\\Valid\\MultipleMethodRouteController',
                'default',
            ],
            'prefix' => '/put',
            'path' => '/put',
            'methods' => [
                'PUT' => true,
            ],
        ],
        [
            'handler' => [
                'Flight\\Routing\\Tests\\Fixtures\\Annotation\\Route\\Valid\\ClassGroupWithoutPath',
                'default',
            ],
            'prefix' => '/put',
            'path' => '/put',
            'methods' => [
                'CONNECT' => true,
                'PUT' => true,
            ],
            'name' => 'class_group@CONNECT_PUT_put',
        ],
        [
            'handler' => 'Flight\\Routing\\Tests\\Fixtures\\Annotation\\Route\\Valid\\Subdir\\BarRequestHandler',
            'prefix' => '/sub-dir/bar',
            'path' => '/sub-dir/bar',
            'methods' => [
                'HEAD' => true,
                'GET' => true,
            ],
            'name' => 'sub-dir:bar',
            'defaults' => [
                'foo' => 'bar',
                'bar' => 'baz',
            ],
        ],
        [
            'handler' => 'Flight\\Routing\\Tests\\Fixtures\\Annotation\\Route\\Valid\\Subdir\\FooRequestHandler',
            'prefix' => '/sub-dir/foo',
            'path' => '/sub-dir/foo',
            'methods' => [
                'HEAD' => true,
                'GET' => true,
            ],
            'name' => 'sub-dir:foo',
            'defaults' => [
                'foo' => 'bar',
                'bar' => 'baz',
            ],
        ],
        [
            'handler' => 'Flight\\Routing\\Tests\\Fixtures\\Annotation\\Route\\Valid\\HomeRequestHandler',
            'prefix' => null,
            'path' => '/',
            'methods' => [
                'HEAD' => true,
                'GET' => true,
            ],
            'name' => 'home',
            'schemes' => [
                'https' => true,
            ],
            'hosts' => [
                'biurad.com' => true,
            ],
            'defaults' => [
                'foo' => 'bar',
                'bar' => 'baz',
            ],
        ],
        [
            'handler' => [
                'Flight\\Routing\\Tests\\Fixtures\\Annotation\\Route\\Valid\\DefaultValueController',
                'hello',
            ],
            'prefix' => '/cool',
            'path' => '/cool/{name=<Symfony>}',
            'methods' => [
                'GET' => true,
                'POST' => true,
            ],
            'name' => 'hello_with_default',
            'placeholders' => [
                'name' => '\\w+',
            ],
        ],
        [
            'handler' => [
                'Flight\\Routing\\Tests\\Fixtures\\Annotation\\Route\\Attribute\\GlobalDefaultsClass',
                'withName',
            ],
            'prefix' => '/defaults',
            'path' => '/defaults/{locale}specific-name',
            'placeholders' => [
                'locale' => 'en|fr',
            ],
            'defaults' => [
                'foo' => 'bar',
            ],
            'methods' => [
                'GET' => true,
            ],
            'name' => 'attribute_specific_name',
        ],
        [
            'handler' => [
                'Flight\\Routing\\Tests\\Fixtures\\Annotation\\Route\\Attribute\\GlobalDefaultsClass',
                'noName',
            ],
            'prefix' => '/defaults',
            'path' => '/defaults/{locale}specific-none',
            'placeholders' => [
                'locale' => 'en|fr',
            ],
            'defaults' => [
                'foo' => 'bar',
            ],
            'methods' => [
                'GET' => true,
                'HEAD' => true,
            ],
            'name' => 'attribute_GET_HEAD_defaults_localespecific_none',
        ],
        [
            'handler' => [
                'Flight\\Routing\\Tests\\Fixtures\\Annotation\\Route\\Valid\\DefaultValueController',
                'hello',
            ],
            'prefix' => '/hello',
            'path' => '/hello/{name:\\w+}',
            'methods' => [
                'GET' => true,
                'POST' => true,
            ],
            'name' => 'hello_without_default',
        ],
        [
            'handler' => [
                'Flight\\Routing\\Tests\\Fixtures\\Annotation\\Route\\Valid\\MethodOnRoutePattern',
                'handleSomething',
            ],
            'prefix' => 'testing',
            'path' => '/testing/',
            'methods' => [
                'GET' => true,
                'HEAD' => true,
            ],
        ],
        [
            'handler' => new ResourceHandler([
                'Flight\\Routing\\Tests\\Fixtures\\Annotation\\Route\\Valid\\RestfulController',
                'User',
            ]),
            'prefix' => '/user',
            'path' => '/user/{id:\\d+}',
            'methods' => [
                'GET' => true,
            ],
            'name' => 'user__restful',
        ],
        [
            'handler' => [
                'Flight\\Routing\\Tests\\Fixtures\\Annotation\\Route\\Valid\\DefaultValueController',
                'action',
            ],
            'prefix' => null,
            'path' => '/{default}/path',
            'methods' => [
                'GET' => true,
                'POST' => true,
            ],
            'name' => 'action',
        ],
    ]
    EOT, debugFormat($routes));
})->setRunTestInSeparateProcess(true);

test('if route path from attribute route can be invalid', function (): void {
    $reader = new AnnotationLoader(new AnnotationReader());
    $reader->listener(new Listener());
    $reader->resource('Flight\Routing\Tests\Fixtures\Annotation\Route\Invalid\PathEmpty');
    $reader->load(Listener::class);
})->throws(InvalidAnnotationException::class, 'Attributed method route path empty');

test('if annotated route from class method can be a restful type', function (): void {
    $reader = new AnnotationLoader(new AnnotationReader());
    $reader->listener(new Listener());
    $reader->resource('Flight\Routing\Tests\Fixtures\Annotation\Route\Invalid\MethodWithResource');
    $reader->load(Listener::class);
})->throws(InvalidAnnotationException::class, 'Restful routing is only supported on attribute route classes.');

test('if annotated route from class with annotated methods can be a restful type', function (): void {
    $reader = new AnnotationLoader(new AnnotationReader());
    $reader->listener(new Listener());
    $reader->resource('Flight\Routing\Tests\Fixtures\Annotation\Route\Invalid\ClassGroupWithResource');
    $reader->load(Listener::class);
})->throws(InvalidAnnotationException::class, 'Restful annotated class cannot contain annotated method(s).');
