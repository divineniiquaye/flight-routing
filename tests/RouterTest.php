<?php declare(strict_types=1);

/*
 * This file is part of Flight Routing.
 *
 * PHP version 8.0 and above required
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 Divine Niiquaye Ibok (https://divinenii.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Flight\Routing\Exceptions\MethodNotAllowedException;
use Flight\Routing\Exceptions\RouteNotFoundException;
use Flight\Routing\Exceptions\UriHandlerException;
use Flight\Routing\Exceptions\UrlGenerationException;
use Flight\Routing\Handlers\CallbackHandler;
use Flight\Routing\Handlers\ResourceHandler;
use Flight\Routing\Handlers\RouteHandler;
use Flight\Routing\RouteCollection;
use Flight\Routing\RouteCompiler;
use Flight\Routing\Router;
use Flight\Routing\ROuteUri as GeneratedUri;
use Flight\Routing\Tests\Fixtures\BlankRequestHandler;
use Laminas\Stratigility\Next;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Uri;
use PHPUnit\Framework as t;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function Laminas\Stratigility\middleware;

test('if method not found in matched route', function (): void {
    $collection = new RouteCollection();
    $collection->add('/hello', ['POST']);

    $router = Router::withCollection($collection);

    try {
        $router->match('GET', new Uri('/hello'));
        $this->fail('Expected a method nor found exception to be thrown');
    } catch (MethodNotAllowedException $e) {
        t\assertSame(['POST'], $e->getAllowedMethods());

        throw $e;
    }
})->throws(
    MethodNotAllowedException::class,
    'Route with "/hello" path requires request method(s) [POST], "GET" is invalid.'
);

test('if scheme not found in matched route', function (): void {
    $collection = new RouteCollection();
    $collection->add('/hello', ['GET'])->scheme('ftp');

    $router = Router::withCollection($collection);
    $router->match('GET', new Uri('http://localhost/hello'));
})->throws(
    UriHandlerException::class,
    'Route with "/hello" path requires request scheme(s) [ftp], "http" is invalid.'
);

test('if host not found in matched route', function (): void {
    $collection = new RouteCollection();
    $collection->add('/hello', ['GET'])->domain('mydomain.com');

    $router = Router::withCollection($collection);
    t\assertNull($router->match('GET', new Uri('//localhost/hello')));
});

test('if route can match a static host', function (): void {
    $collection = new RouteCollection();
    $collection->add('/world', ['GET'])->domain('hello.com');

    $router = Router::withCollection($collection);
    $route = $router->match('GET', new Uri('//hello.com/world'));
    t\assertSame('hello.com', \array_key_first($route['hosts']));
    t\assertCount(0, $route['arguments'] ?? []);
});

test('if route can match a dynamic host', function (): void {
    $collection = new RouteCollection();
    $collection->add('/world', ['GET'])->domain('hello.{tld}');

    $router = Router::withCollection($collection);
    $route = $router->match('GET', new Uri('//hello.ghana/world'));
    t\assertSame('hello.{tld}', \array_key_first($route['hosts']));
    t\assertSame(['tld' => 'ghana'], $route['arguments'] ?? []);
});

test('if route cannot be found by name to generate a reversed uri', function (): void {
    $router = Router::withCollection();
    $router->generateUri('hello');
})->throws(UrlGenerationException::class, 'Route "hello" does not exist.');

test('if route handler can be intercepted by middlewares', function (): void {
    $collection = new RouteCollection();
    $collection->add('/{name}', ['GET'], new CallbackHandler(fn (ServerRequestInterface $req): string => $req->getAttribute('hello')))->piped('guard');

    $router = Router::withCollection($collection);
    $router->pipe(middleware(function (ServerRequestInterface $req, RequestHandlerInterface $handler) {
        t\assertIsArray($route = $req->getAttribute(Router::class));
        t\assertNotEmpty($route);
        t\assertArrayHasKey('name', $hello = $route['arguments'] ?? []);

        return $handler->handle($req->withAttribute('hello', 'Hello '.$hello['name']));
    }));
    $router->pipes('guard', middleware(function (ServerRequestInterface $req, RequestHandlerInterface $handler) {
        $route = $req->getAttribute(Router::class);

        if ('divine' !== $route['arguments']['name']) {
            throw new \RuntimeException('Expected name to be "divine".');
        }

        return $handler->handle($req);
    }));

    $response = $router->process(new ServerRequest(Router::METHOD_GET, '/hello'), $h = new RouteHandler(new Psr17Factory()));
    t\assertSame('Hello divine', (string) $response->getBody());
    $router->process(new ServerRequest(Router::METHOD_GET, '/frank'), $h);
})->throws(\RuntimeException::class, 'Expected name to be "divine".');

test('if route cannot be found', function (): void {
    $handler = new RouteHandler(new Psr17Factory());
    $handler->handle(new ServerRequest(Router::METHOD_GET, '/hello'));
})->throws(
    RouteNotFoundException::class,
    'Unable to find the controller for path "/hello". The route is wrongly configured.'
);

test('if route not found exception can be overridden', function (): void {
    $handler = new RouteHandler($f = new Psr17Factory());
    $router = new Router();
    $router->pipe(middleware(
        function (ServerRequestInterface $req, RequestHandlerInterface $handler) use ($f) {
            if (null === $req->getAttribute(Router::class)) {
                t\assertInstanceOf(Next::class, $handler);
                $response = $f->createResponse('OPTIONS' === $req->getMethod() ? 200 : 204);

                if (false === $h = $req->getAttribute('override')) {
                    return $response;
                } // This will break the middleware chain.
                $req = $req->withAttribute(RouteHandler::OVERRIDE_NULL_ROUTE, $h ?? $response);
            }

            return $handler->handle($req);
        }
    ));

    $req = new ServerRequest(Router::METHOD_GET, '/hello');
    $res1 = $router->process($req->withAttribute('override', false), $handler);
    $res2 = $router->process($req->withAttribute('override', false)->withMethod('OPTIONS'), $handler);
    $res3 = $router->process($req->withAttribute('override', true), $handler);
    t\assertSame([204, 200, 200], [$res1->getStatusCode(), $res2->getStatusCode(), $res3->getStatusCode()]);
});

test('if router export method can work with closures, __set_state and resource handler', function (): void {
    t\assertSame(
        "[[], unserialize('O:11:\"ArrayObject\":4:{i:0;i:0;i:1;a:3:{i:0;i:1;i:1;i:2;i:2;i:3;}i:2;a:0:{}i:3;N;}'), ".
        "Flight\Routing\Handlers\ResourceHandler(['ShopHandler', 'User']), ".
        "Flight\Routing\Tests\Fixtures\BlankRequestHandler::__set_state(['isDone' => false, 'attributes' => ['a' => [1, 2, 3]]]]",
        Router::export([[], new \ArrayObject([1, 2, 3]), new ResourceHandler('ShopHandler', 'user'), new BlankRequestHandler(['a' => [1, 2, 3]])])
    );

    Router::export(\Closure::bind(fn () => 'Failed', null));
    $this->fail('Expected an exception to be thrown as closure cannot be serialized');
})->throws(\Exception::class, "Serialization of 'Closure' is not allowed");

test('if router can be resolvable', function (int $cache): void {
    $file = __DIR__."/../tests/Fixtures/cached/{$cache}/compiled.php";
    $collection = static function (RouteCollection $mergedCollection): void {
        $demoCollection = new RouteCollection();
        $demoCollection->add('/admin/post/', [Router::METHOD_POST]);
        $demoCollection->add('/admin/post/new', [Router::METHOD_POST]);
        $demoCollection->add('/admin/post/{id}', [Router::METHOD_POST])->placeholder('id', '\d+');
        $demoCollection->add('/admin/post/{id}/edit', [Router::METHOD_PATCH])->placeholder('id', '\d+');
        $demoCollection->add('/admin/post/{id}/delete', [Router::METHOD_DELETE])->placeholder('id', '\d+');
        $demoCollection->add('/blog/', [Router::METHOD_GET]);
        $demoCollection->add('/blog/rss.xml', [Router::METHOD_GET]);
        $demoCollection->add('/blog/page/{page}', [Router::METHOD_GET])->placeholder('id', '\d+');
        $demoCollection->add('/blog/posts/{page}', [Router::METHOD_GET])->placeholder('id', '\d+');
        $demoCollection->add('/blog/comments/{id}/new', [Router::METHOD_GET])->placeholder('id', '\d+');
        $demoCollection->add('/blog/search', [Router::METHOD_GET]);
        $demoCollection->add('/login', [Router::METHOD_POST]);
        $demoCollection->add('/logout', [Router::METHOD_POST]);
        $demoCollection->add('/', [Router::METHOD_GET]);
        $demoCollection->prototype(true)->prefix('/{_locale}/');
        $demoCollection->method(Router::METHOD_CONNECT);
        $mergedCollection->group('demo.', $demoCollection)->default('_locale', 'en')->placeholder('_locale', 'en|fr');

        $chunkedCollection = new RouteCollection();
        $chunkedCollection->domain('http://localhost')->scheme('https', 'http');

        for ($i = 0; $i < 100; ++$i) {
            $chunkedCollection->get('/chuck'.$i.'/{a}/{b}/{c}/')->bind('_'.$i);
        }
        $mergedCollection->group('chuck_', $chunkedCollection);

        $groupOptimisedCollection = new RouteCollection();
        $groupOptimisedCollection->add('/a/11', [Router::METHOD_GET])->bind('a_first');
        $groupOptimisedCollection->add('/a/22', [Router::METHOD_GET])->bind('a_second');
        $groupOptimisedCollection->add('/a/333', [Router::METHOD_GET])->bind('a_third');
        $groupOptimisedCollection->add('/a/333/', [Router::METHOD_POST], (object) [2, 4])->bind('a_third_1');
        // $groupOptimisedCollection->add('/{param}', [Router::METHOD_GET])->bind('a_wildcard');
        $groupOptimisedCollection->add('/a/44/', [Router::METHOD_GET])->bind('a_fourth');
        $groupOptimisedCollection->add('/a/55/', [Router::METHOD_GET])->bind('a_fifth');
        $groupOptimisedCollection->add('/nested/{param}', [Router::METHOD_GET])->bind('nested_wildcard');
        $groupOptimisedCollection->add('/nested/group/a/', [Router::METHOD_GET])->bind('nested_a');
        $groupOptimisedCollection->add('/nested/group/b/', [Router::METHOD_GET])->bind('nested_b');
        $groupOptimisedCollection->add('/nested/group/c/', [Router::METHOD_GET])->bind('nested_c');
        $groupOptimisedCollection->add('/a/66/', [Router::METHOD_GET], 'phpinfo');

        $groupOptimisedCollection->add('/slashed/group/', [Router::METHOD_GET])->bind('slashed_a');
        $groupOptimisedCollection->add('/slashed/group/b/', [Router::METHOD_GET])->bind('slashed_b');
        $groupOptimisedCollection->add('/slashed/group/c/', [Router::METHOD_GET])->bind('slashed_c');

        $mergedCollection->group('', $groupOptimisedCollection);
        $mergedCollection->sort();
    };

    if ($cache <= 1) {
        if (\file_exists($dir = __DIR__.'/../tests/Fixtures/cached/') && 0 === $cache) {
            foreach ([
                $dir.'1/compiled.php',
                $dir.'3/compiled.php',
                $dir.'1',
                $dir.'3',
                $dir,
            ] as $cached) {
                \is_dir($cached) ? @\rmdir($cached) : @\unlink($cached);
            }
        }
        $router = new Router(cache: 1 === $cache ? $file : null);
        $router->setCollection($collection);
    } else {
        $collection($collection = new RouteCollection());
        $router = Router::withCollection($collection);
        $router->setCompiler(new RouteCompiler());

        if (3 === $cache) {
            $router->setCache($file);
        }
    }

    $route1 = $router->match(Router::METHOD_GET, new Uri('/fr/blog'));
    $route2 = $router->matchRequest(new ServerRequest(Router::METHOD_GET, 'http://localhost/chuck12/hello/1/2'));
    $route3 = $router->matchRequest(new ServerRequest(Router::METHOD_GET, '/a/333'));
    $route4 = $router->matchRequest(new ServerRequest(Router::METHOD_POST, '/a/333'));
    $genRoute = $router->generateUri('chuck__12', ['a', 'b', 'c'])->withQuery(['h', 'a' => 'b']);

    t\assertCount(128, $router->getCollection());
    t\assertSame($router->match('GET', new Uri('/a/66/')), $router->match('GET', new Uri('/a/66')));
    t\assertSame('/chuck12/a/b/c/?0=h&a=b#yes', (string) $genRoute->withFragment('yes'));
    t\assertSame('//example.com:8080/a/11', (string) $router->generateUri('a_first', [], GeneratedUri::ABSOLUTE_URL)->withPort(8080));
    t\assertNull($router->match('GET', new Uri('/None')));
    t\assertEquals(<<<'EOT'
    [
        [
            'handler' => null,
            'prefix' => null,
            'path' => '/{_locale}/blog/',
            'methods' => [
                'GET' => true,
                'CONNECT' => true,
            ],
            'defaults' => [
                '_locale' => 'en',
            ],
            'placeholders' => [
                '_locale' => 'en|fr',
            ],
            'name' => 'demo.GET_CONNECT_locale_blog_',
            'arguments' => [
                '_locale' => 'fr',
            ],
        ],
        [
            'handler' => null,
            'prefix' => '/chuck12',
            'path' => '/chuck12/{a}/{b}/{c}/',
            'schemes' => [
                'https' => true,
                'http' => true,
            ],
            'hosts' => [
                'localhost' => true,
            ],
            'methods' => [
                'GET' => true,
                'HEAD' => true,
            ],
            'name' => 'chuck__12',
            'arguments' => [
                'a' => 'hello',
                'b' => '1',
                'c' => '2',
            ],
        ],
        [
            'handler' => null,
            'prefix' => '/a/333',
            'path' => '/a/333',
            'methods' => [
                'GET' => true,
            ],
            'name' => 'a_third',
        ],
        [
            'handler' => (object) [
                2,
                4,
            ],
            'prefix' => '/a/333',
            'path' => '/a/333/',
            'methods' => [
                'POST' => true,
            ],
            'name' => 'a_third_1',
        ],
    ]
    EOT, debugFormat([$route1, $route2, $route3, $route4]));
})->with([0, 1, 1, 2, 3, 3]);
