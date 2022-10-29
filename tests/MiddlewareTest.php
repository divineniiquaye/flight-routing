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

use Flight\Routing\Exceptions\RouteNotFoundException;
use Flight\Routing\Handlers\FileHandler;
use Flight\Routing\Handlers\RouteHandler;
use Flight\Routing\Middlewares\PathMiddleware;
use Flight\Routing\Middlewares\UriRedirectMiddleware;
use Flight\Routing\Router;
use Flight\Routing\Tests\Fixtures;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Uri;
use PHPUnit\Framework as t;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

dataset('paths_data', [
    // name => [$uriPath, $requestPath, $expectedPath, $status ]
    'root-without-prefix-tail_1' => ['/foo', '/foo', '', 200],
    'root-without-prefix-tail_2' => ['/foo', '/foo/', '/foo', 301],
    'root-without-prefix-tail_3' => ['/foo', '/foo@', '', 404],
    'root-without-prefix-tail_4' => ['/[{bar}]', '/', '', 200],
    'root-without-prefix-tail_5' => ['/[{bar}]', '/foo/', '/foo', 301],
    'root-without-prefix-tail_6' => ['/[{bar}]', '/foo', '', 200],
    'root-with-prefix-tail_1' => ['/foo/', '/foo/', '', 200],
    'root-with-prefix-tail_2' => ['/foo/', '/foo@', '', 404],
    'root-with-prefix-tail_3' => ['/foo/', '/foo', '/foo/', 301],
    'root-with-prefix-tail_4' => ['/[{bar}]/', '/', '', 200],
    'root-with-prefix-tail_5' => ['/[{bar}]/', '/foo', '/foo/', 301],
    'root-with-prefix-tail_6' => ['/[{bar}]/', '/foo/', '', 200],
]);

dataset('redirects', function (): Generator {
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
});

test('if path middleware constructor is ok', function (): void {
    $middleware = new PathMiddleware();
    $response = $middleware->process(new ServerRequest('GET', '/foo'), new Fixtures\BlankRequestHandler());

    t\assertInstanceOf(ResponseInterface::class, $response);
    t\assertEquals(200, $response->getStatusCode());
    t\assertFalse($response->hasHeader('Location'));
});

test('if path middleware process the right status code', function (): void {
    $handler = new RouteHandler(new Psr17Factory());
    $router = Router::withCollection();
    $router->getCollection()->get('/foo', fn (ResponseFactoryInterface $f) => $f->createResponse());
    $router->pipe(new PathMiddleware());

    $response = $router->process(new ServerRequest('GET', '/foo'), $handler);
    t\assertInstanceOf(ResponseInterface::class, $response);
    t\assertEquals(204, $response->getStatusCode());
    t\assertFalse($response->hasHeader('Location'));
});

test('if path middleware can process from a subfolder correctly', function (): void {
    $subFolder = null;
    $handler = new RouteHandler(new Psr17Factory());
    $router = Router::withCollection();
    $router->pipe(new PathMiddleware());
    $router->getCollection()->get('/foo/', function (ServerRequestInterface $req, ResponseFactoryInterface $f) use (&$subFolder) {
        $subFolder = $req->getAttribute(PathMiddleware::SUB_FOLDER);
        $res = $f->createResponse();
        $res->getBody()->write(\sprintf('Routing from subfolder %s as base root', $subFolder));

        return $res;
    });

    $request = new ServerRequest(Router::METHOD_GET, '/build/foo', [], null, '1.1', ['PATH_INFO' => '/foo']);
    $response = $router->process($request, $handler);
    t\assertEquals('/build', $subFolder);
    t\assertEquals(302, $response->getStatusCode());
    t\assertEquals('/foo/', $response->getHeaderLine('Location'));
});

test('if path middleware with expected 301', function (string $uriPath, string $requestPath, string $expectedPath, int $expectsStatus): void {
    $router = Router::withCollection();
    $router->pipe(new PathMiddleware(true));
    $router->getCollection()->get($uriPath, function (ResponseFactoryInterface $f): ResponseInterface {
        $res = $f->createResponse()->withHeader('Content-Type', FileHandler::MIME_TYPE['html']);
        $res->getBody()->write('Hello World');

        return $res;
    });

    try {
        $response = $router->process(new ServerRequest(Router::METHOD_GET, $requestPath), new RouteHandler(new Psr17Factory()));
    } catch (RouteNotFoundException $e) {
        t\assertEquals($expectsStatus, $e->getCode());

        return;
    }

    t\assertEquals($expectsStatus, $response->getStatusCode());
    t\assertEquals($expectedPath, $response->getHeaderLine('Location'));
})->with('paths_data');

test('if path middleware with expected 301 => 302', function (string $uriPath, string $requestPath, string $expectedPath, int $expectsStatus): void {
    $router = Router::withCollection();
    $router->pipe(new PathMiddleware());
    $router->getCollection()->get($uriPath, function (ResponseFactoryInterface $f): ResponseInterface {
        $res = $f->createResponse()->withHeader('Content-Type', FileHandler::MIME_TYPE['html']);
        $res->getBody()->write('Hello World');

        return $res;
    });

    try {
        $response = $router->process(new ServerRequest(Router::METHOD_GET, $requestPath), new RouteHandler(new Psr17Factory()));
    } catch (RouteNotFoundException $e) {
        t\assertEquals($expectsStatus, $e->getCode());

        return;
    }

    t\assertEquals(301 === $expectsStatus ? 302 : $expectsStatus, $response->getStatusCode());
    t\assertEquals($expectedPath, $response->getHeaderLine('Location'));
})->with('paths_data');

test('if path middleware with expected 301 => 307', function (string $uriPath, string $requestPath, string $expectedPath, int $expectsStatus): void {
    $router = Router::withCollection();
    $router->pipe(new PathMiddleware(false, true));
    $router->getCollection()->get($uriPath, function (ResponseFactoryInterface $f): ResponseInterface {
        $res = $f->createResponse()->withHeader('Content-Type', FileHandler::MIME_TYPE['html']);
        $res->getBody()->write('Hello World');

        return $res;
    });

    try {
        $response = $router->process(new ServerRequest(Router::METHOD_GET, $requestPath), new RouteHandler(new Psr17Factory()));
    } catch (RouteNotFoundException $e) {
        t\assertEquals($expectsStatus, $e->getCode());

        return;
    }

    t\assertEquals(301 === $expectsStatus ? 307 : $expectsStatus, $response->getStatusCode());
    t\assertEquals($expectedPath, $response->getHeaderLine('Location'));
})->with('paths_data');

test('if path middleware with expected 301 => 308', function (string $uriPath, string $requestPath, string $expectedPath, int $expectsStatus): void {
    $router = Router::withCollection();
    $router->pipe(new PathMiddleware(true, true));
    $router->getCollection()->get($uriPath, function (ResponseFactoryInterface $f): ResponseInterface {
        $res = $f->createResponse()->withHeader('Content-Type', FileHandler::MIME_TYPE['html']);
        $res->getBody()->write('Hello World');

        return $res;
    });

    try {
        $response = $router->process(new ServerRequest(Router::METHOD_GET, $requestPath), new RouteHandler(new Psr17Factory()));
    } catch (RouteNotFoundException $e) {
        t\assertEquals($expectsStatus, $e->getCode());

        return;
    }

    t\assertEquals(301 === $expectsStatus ? 308 : $expectsStatus, $response->getStatusCode());
    t\assertEquals($expectedPath, $response->getHeaderLine('Location'));
})->with('paths_data');

test('if uri-redirect middleware can process the right status code', function (array $redirects, string $expected): void {
    $router = Router::withCollection();
    $router->pipe(new UriRedirectMiddleware($redirects));
    $router->getCollection()->get($expected, Fixtures\BlankRequestHandler::class);

    $res = $router->process(new ServerRequest(Router::METHOD_GET, $expected), new RouteHandler(new Psr17Factory()));
    t\assertInstanceOf(ResponseInterface::class, $res);
    t\assertSame(204, $res->getStatusCode());
})->with('redirects');

test('if uri-redirect middleware can redirect old path to new as 301', function (array $redirects, string $expected): void {
    $router = Router::withCollection();
    $router->pipe(new UriRedirectMiddleware($redirects));
    $router->getCollection()->get($expected, Fixtures\BlankRequestHandler::class);

    $actual = \key($redirects);
    $res = $router->process(new ServerRequest(Router::METHOD_GET, $actual), new RouteHandler(new Psr17Factory()));
    t\assertInstanceOf(ResponseInterface::class, $res);
    t\assertSame(301, $res->getStatusCode());
    t\assertSame((string) $redirects[$actual], $res->getHeaderLine('Location'));
})->with('redirects');

test('if uri-redirect middleware can redirect old path to new as 308', function (array $redirects, string $expected): void {
    $router = Router::withCollection();
    $router->pipe(new UriRedirectMiddleware($redirects, true));
    $router->getCollection()->get($expected, Fixtures\BlankRequestHandler::class);

    $actual = \key($redirects);
    $res = $router->process(new ServerRequest(Router::METHOD_GET, $actual), new RouteHandler(new Psr17Factory()));
    t\assertInstanceOf(ResponseInterface::class, $res);
    t\assertSame(308, $res->getStatusCode());
    t\assertSame((string) $redirects[$actual], $res->getHeaderLine('Location'));
})->with('redirects');

test('if uri-redirect middleware can redirect a full path to new', function (): void {
    $router = Router::withCollection();
    $router->pipe(new UriRedirectMiddleware(['/user/\d+' => '#/account/me']));
    $router->getCollection()->get('/account/me', Fixtures\BlankRequestHandler::class);

    $uri = new Uri('/user/23?page=settings#notification');
    $res = $router->process(new ServerRequest(Router::METHOD_GET, $uri), new RouteHandler(new Psr17Factory()));
    t\assertInstanceOf(ResponseInterface::class, $res);
    t\assertSame(301, $res->getStatusCode());
    t\assertSame('/account/me?page=settings#notification', $res->getHeaderLine('Location'));
});
