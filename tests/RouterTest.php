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

use DivineNii\Invoker\Exceptions\NotEnoughParametersException;
use Flight\Routing\Exceptions\InvalidControllerException;
use Flight\Routing\Exceptions\MethodNotAllowedException;
use Flight\Routing\Exceptions\RouteNotFoundException;
use Flight\Routing\Exceptions\UriHandlerException;
use Flight\Routing\Exceptions\UrlGenerationException;
use Flight\Routing\Generator\GeneratedUri;
use Flight\Routing\Handlers\ResourceHandler;
use Flight\Routing\Handlers\RouteHandler;
use Flight\Routing\Interfaces\RouteMatcherInterface;
use Flight\Routing\Interfaces\UrlGeneratorInterface;
use Flight\Routing\Route;
use Flight\Routing\RouteCollection;
use Flight\Routing\RouteMatcher;
use Flight\Routing\Router;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Uri;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;

use function Laminas\Stratigility\middleware;

/**
 * RouterTest.
 */
class RouterTest extends BaseTestCase
{
    public function testConstructor(): void
    {
        $router = new Router();
        $this->assertInstanceOf(MiddlewareInterface::class, $router);
        $this->assertInstanceOf(UrlGeneratorInterface::class, $router);
        $this->assertInstanceOf(RouteMatcherInterface::class, $router);

        $this->assertInstanceOf(RouteMatcher::class, $router->getMatcher());
    }

    public function testAddRoute(): void
    {
        $routes = [new Route('/foo'), new Route('/bar'), new Route('/baz')];

        $router = Router::withCollection();
        $router->addRoute(...$routes);

        $this->assertCount(3, $router->getMatcher()->getRoutes());
    }

    public function testMiddlewareOnRoute(): void
    {
        $router = Router::withCollection();
        $route = new Route('/phpinfo', Router::METHOD_GET, 'phpinfo');
        $request = new ServerRequest($route->getMethods()[0], $route->getPath());

        $router->addRoute($route);
        $router->pipe(new Fixtures\PhpInfoListener());

        $response = $router->process($request, new RouteHandler(new Psr17Factory()));

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
    }

    public function testAddRouteListenerWithException(): void
    {
        $router = Router::withCollection();
        $route = new Route('/phpinfo', Router::METHOD_GET, 'phpinfo');
        $request = new ServerRequest($route->getMethods()[0], $route->getPath());

        $router->addRoute($route);

        try {
            $this->assertInstanceOf(ResponseInterface::class, $router->process($request, new RouteHandler(new Psr17Factory())));
        } catch (NotEnoughParametersException $e) {
            $this->assertEquals(
                'Unable to invoke the callable because no value was given for parameter 1 ($what)',
                $e->getMessage()
            );
        }
    }

    public function testSetNamespace(): void
    {
        $router = Router::withCollection();
        $route = new Route('/foo', Router::METHOD_GET, '\\Fixtures\\BlankRequestHandler');

        $router->addRoute($route->namespace('Flight\\Routing\\Tests'));

        $request = new ServerRequest($route->getMethods()[0], $route->getPath());
        $route = $router->matchRequest($request);

        $this->assertInstanceOf(Route::class, $route);
        $this->assertInstanceOf(ResponseInterface::class, $router->process($request, new RouteHandler(new Psr17Factory())));
    }

    public function testGenerateUri(): void
    {
        $route = new Route('/foo');
        $path = '.' . $route->getPath();

        $router = Router::withCollection();
        $router->addRoute($route->bind('hello'), Route::to('https://example.com/foo')->bind('world'));

        $this->assertSame($path, (string) $router->generateUri($route->getName(), [], GeneratedUri::RELATIVE_PATH));
        $this->assertSame('https://example.com:8080/foo', (string) $router->generateUri('world', [], GeneratedUri::ABSOLUTE_URL)->withPort('8080'));
    }

    public function testGenerateUriWithDomain(): void
    {
        $route = new Route('/foo');
        $path = 'http://biurad.com' . $route->getPath();

        $router = Router::withCollection();
        $router->addRoute($route->domain('http://biurad.com')->bind('hello'));

        $this->assertSame($path, (string) $router->generateUri($route->getName()));
    }

    public function testGenerateUriWithQuery(): void
    {
        $route = new Route('/foo');
        $path = $route->getPath() . '?hello=world&first=1';

        $router = Router::withCollection();
        $router->addRoute($route->bind('hello'));

        $this->assertSame(
            $path,
            (string) $router->generateUri($route->getName())->withQuery(['hello' => 'world', 'first' => 1])
        );
    }

    public function testGenerateUriException(): void
    {
        $router = Router::withCollection();

        $this->expectExceptionMessage('Unable to generate a URL for the named route "none" as such route does not exist.');
        $this->expectException(UrlGenerationException::class);

        $router->generateUri('none');
    }

    public function testMatch(): void
    {
        $routes = [
            new Route('/path1'),
            new Route('/path2'),
            new Route('/path3'),
            new Route('/path4'),
            new Route('/path5'),
        ];

        $router = Router::withCollection();
        $router->addRoute(...$routes);

        $route = $router->match($routes[2]->getMethods()[1], new Uri($routes[2]->getPath()));

        $this->assertInstanceOf(Route::class, $route);
    }

    public function testMatchForUnAllowedMethod(): void
    {
        $routes = [
            new Route('/path1', Router::METHOD_PATCH),
            new Route('/path2', Router::METHOD_PATCH),
            new Route('/path3', Router::METHOD_PATCH),
            new Route('/path4', Router::METHOD_PATCH),
            new Route('/path5', Router::METHOD_PATCH),
        ];

        $router = Router::withCollection();
        $router->addRoute(...$routes);

        // the given exception message should be tested through exceptions factory...
        $this->expectExceptionObject(new MethodNotAllowedException([Router::METHOD_PATCH], '/path3', Router::METHOD_GET));

        try {
            $router->match('GET', new Uri($routes[2]->getPath()));
        } catch (MethodNotAllowedException $e) {
            $this->assertSame($routes[2]->getMethods(), $e->getAllowedMethods());

            throw $e;
        }
    }

    public function testMatchForUndefinedRoute(): void
    {
        $router = new Router();
        $router->setCollection(static function (RouteCollection $collection): void {
            $collection->routes([new Route('/foo'), new Route('/bar'), new Route('/baz')]);
        });

        $this->expectExceptionMessage('Unable to find the controller for path "/". The route is wrongly configured.');
        $this->expectException(RouteNotFoundException::class);

        $router->process(new ServerRequest(Router::METHOD_GET, '/'), new RouteHandler(new Psr17Factory()));
    }

    public function testHandleResponse(): void
    {
        $router = Router::withCollection();
        $router->addRoute($route = new Route(
            '/foo',
            Router::METHOD_GET,
            function (ResponseFactoryInterface $responseFactory): ResponseInterface {
                ($response = $responseFactory->createResponse())->getBody()->write('I am a GET method');

                return $response;
            }
        ));

        $response = $router->process(new ServerRequest(Router::METHOD_GET, $route->getPath()), new RouteHandler(new Psr17Factory()));

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame('I am a GET method', (string) $response->getBody());
    }

    public function testHandleResponseOnSubDirectory(): void
    {
        $subPath = '/sub-directory';

        $router = Router::withCollection();
        $router->addRoute($route = new Route(
            '/foo',
            Router::METHOD_GET,
            static function (ResponseFactoryInterface $responseFactory): ResponseInterface {
                ($response = $responseFactory->createResponse())->getBody()->write('I am a GET method');

                return $response;
            }
        ));

        $response = $router->process(
            new ServerRequest(Router::METHOD_GET, $subPath . $route->getPath(), [], null, '1.1', ['PATH_INFO' => $route->getPath()]),
            new RouteHandler(new Psr17Factory())
        );

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame('I am a GET method', (string) $response->getBody());
    }

    public function testHandleResponseOnDirectory(): void
    {
        $subPath = '/directory';

        $router = Router::withCollection();
        $router->addRoute($route = new Route(
            '/foo',
            Router::METHOD_GET,
            function (ResponseFactoryInterface $responseFactory): ResponseInterface {
                ($response = $responseFactory->createResponse())->getBody()->write('I am a GET method');

                return $response;
            }
        ));

        $response = $router->process(
            new ServerRequest(Router::METHOD_GET, $route->getPath(), [], null, '1.1', ['SCRIPT_NAME' => $subPath]),
            new RouteHandler(new Psr17Factory())
        );

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame('I am a GET method', (string) $response->getBody());
    }

    public function testEmptyRequestHandler(): void
    {
        $middlewares = [
            new Fixtures\BlankMiddleware(),
            new Fixtures\BlankMiddleware(),
        ];

        $pipeline = Router::withCollection();
        $pipeline->pipe(...$middlewares);

        $this->expectExceptionMessage('Unable to find the controller for path "test". The route is wrongly configured.');
        $this->expectException(RouteNotFoundException::class);

        $pipeline->process(new ServerRequest('GET', 'test'), new RouteHandler(new Psr17Factory()));
    }

    /**
     * @dataProvider hasParametersData
     */
    public function testAddParameters(string $path, string $body): void
    {
        $route = new Route(
            '/{cool}',
            Router::METHOD_GET,
            function ($cool, string $name): string {
                return "My name is {$name} with id: {$cool}";
            }
        );

        $collection = new RouteCollection();
        $collection->add($route);
        $collection->assert('cool', ['23', 'me'])->argument('name', 'Divine');

        $router = Router::withCollection($collection);

        try {
            $response = $router->process(new ServerRequest(Router::METHOD_GET, $path), new RouteHandler(new Psr17Factory()));
        } catch (RouteNotFoundException $e) {
            $this->assertEquals($e->getMessage(), 'Unable to find the controller for path "/none". The route is wrongly configured.');

            return;
        }

        $this->assertSame($body, (string) $response->getBody());
    }

    public function testHandleWithMiddlewares(): void
    {
        $route = new Route('/foo', Route::DEFAULT_METHODS, 'phpinfo');

        $middlewares = [
            new Fixtures\BlankMiddleware(),
            new Fixtures\BlankMiddleware(),
            middleware([new Fixtures\BlankMiddleware(), 'process']),
        ];

        $router = Router::withCollection();
        $router->addRoute($route);
        $router->pipe(...$middlewares);

        $response = $router->process(
            new ServerRequest($route->getMethods()[0], $route->getPath(), [], null, '1.1', ['Broken' => 'test']),
            new RouteHandler(new Psr17Factory())
        );

        $this->assertTrue($middlewares[0]->isRunned());
        $this->assertTrue($middlewares[1]->isRunned());
        $this->assertTrue($response->hasHeader('Middleware'));
        $this->assertEquals('broken', $response->getHeaderLine('Middleware-Broken'));
    }

    public function testHandleWithMiddlewareOnRoute(): void
    {
        $route1 = new Route('/foo');
        $route2 = Route::to('/bar', Router::METHOD_PURGE)->bind('test')->piped('ping');
        $handler = function (ServerRequestInterface $request, ResponseFactoryInterface $factory): ResponseInterface {
            $this->assertArrayHasKey('Broken', $request->getServerParams());
            $this->assertInstanceOf(Route::class, $request->getAttribute(Route::class));

            ($response = $factory->createResponse())->getBody()->write(\sprintf('I am a %s method', \strtoupper($request->getMethod())));

            return $response;
        };

        $route1->run($handler);
        $route2->run($handler);

        ($router = Router::withCollection())->addRoute($route1, $route2);
        $router->pipe($middleware = new Fixtures\BlankMiddleware());
        $router->pipes('ping', new Fixtures\RouteMiddleware());

        $handler = new RouteHandler(new Psr17Factory());
        $request1 = new ServerRequest($route1->getMethods()[0], $route1->getPath(), [], null, '1.1', ['Broken' => 'test']);
        $request2 = new ServerRequest($route2->getMethods()[0], $route2->getPath(), [], null, '1.1', ['Broken' => 'test']);

        foreach ([$request1, $request2] as $request) {
            $response = $router->process($request, $handler);
            $method = $request->getMethod();

            if ($response->hasHeader('NamedRoute')) {
                $this->assertEquals($route2->getName(), $response->getHeaderLine('NamedRoute'));
            }

            $this->assertTrue($middleware->isRunned());
            $this->assertTrue($response->hasHeader('Middleware'));
            $this->assertEquals('broken', $response->getHeaderLine('Middleware-Broken'));
            $this->assertSame(\sprintf('I am a %s method', $method), (string) $response->getBody());
        }
    }

    public function testHandleWithBrokenMiddleware(): void
    {
        $route = new Route('/foo');

        $middlewares = [
            new Fixtures\BlankMiddleware(),
            new Fixtures\BlankMiddleware(true),
            new Fixtures\BlankMiddleware(),
        ];

        $router = Router::withCollection();
        $router->addRoute($route);
        $router->pipe(...$middlewares);

        $router->process(new ServerRequest($route->getMethods()[0], $route->getPath()), new RouteHandler(new Psr17Factory()));

        $this->assertTrue($middlewares[0]->isRunned());
        $this->assertTrue($middlewares[1]->isRunned());
        $this->assertFalse($middlewares[2]->isRunned());
    }

    public function testHandleRouteHandlerAsResponse(): void
    {
        $route = new Route('/foo');
        $route->run(new Response(200, ['Response' => 'Controller']));

        $router = new Router();
        $router->addRoute($route);
        $response = $router->process(new ServerRequest($route->getMethods()[0], $route->getPath()), new RouteHandler(new Psr17Factory()));

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals('Controller', $response->getHeaderLine('Response'));
    }

    public function testHandleForUnAllowedMethod(): void
    {
        $routes = [new Route('/foo'), new Route('/baz', Router::METHOD_CONNECT, 'phpinfo'), new Route('/bar')];

        $router = Router::withCollection();
        $router->addRoute(...$routes);

        // the given exception message should be tested through exceptions factory...
        $this->expectException(MethodNotAllowedException::class);

        try {
            $router->process(new ServerRequest(Router::METHOD_GET, $routes[1]->getPath()), new RouteHandler(new Psr17Factory()));
        } catch (MethodNotAllowedException $e) {
            $this->assertSame($routes[1]->getMethods(), $e->getAllowedMethods());

            throw $e;
        }
    }

    public function testHandleForUndefinedRoute(): void
    {
        $routes = [new Route('/foo'), new Route('/bar'), new Route('/baz')];

        $router = Router::withCollection();
        $router->addRoute(...$routes);

        $this->expectExceptionMessage('Unable to find the controller for path "/". The route is wrongly configured.');
        $this->expectException(RouteNotFoundException::class);

        $router->process(new ServerRequest($routes[0]->getMethods()[0], '/'), new RouteHandler(new Psr17Factory()));
    }

    public function testHandleForUndefinedScheme(): void
    {
        $routes = [new Route('/foo'), new Route('/bar'), new Route('/baz')];
        $routes[0]->scheme('ftp');

        $router = Router::withCollection();
        $router->addRoute(...$routes);

        $this->expectExceptionMessage('Route with "/foo" path is not allowed on requested uri "http://localost/foo" with invalid scheme, supported scheme(s): [ftp].');
        $this->expectException(UriHandlerException::class);

        $router->process(new ServerRequest($routes[0]->getMethods()[0], 'http://localost/foo'), new RouteHandler(new Psr17Factory()));
    }

    public function testHandleDomainAndPort(): void
    {
        $route = new Route('/foo', Route::DEFAULT_METHODS, 'phpinfo');

        $route->domain('localhost.com:8000');

        $router = Router::withCollection();
        $router->addRoute($route);

        $requestPath = 'http://localhost.com:8000' . $route->getPath();
        $response = $router->process(new ServerRequest($route->getMethods()[0], $requestPath), new RouteHandler(new Psr17Factory()));
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testHandleForUndefinedDomain(): void
    {
        $route = new Route('/foo', Route::DEFAULT_METHODS, 'phpinfo');
        $route->domain('{foo}.biurad.com');

        $router = Router::withCollection();
        $router->addRoute($route);

        $this->expectExceptionMessage('Route with "/foo" path is not allowed on requested uri "http://localhost.com/foo" as uri host is invalid.');
        $this->expectException(UriHandlerException::class);

        $requestPath = 'http://localhost.com' . $route->getPath();
        $router->process(new ServerRequest($route->getMethods()[0], $requestPath), new RouteHandler(new Psr17Factory()));
    }

    /**
     * @dataProvider handleNamespaceData
     *
     * @param string|string[] $controller
     */
    public function testHandleWithNamespace(string $namespace, $controller): void
    {
        $route = new Route('/namespace', Router::METHOD_GET, $controller);

        $router = Router::withCollection();
        $router->addRoute($route->namespace($namespace));

        $response = $router->matchRequest(new ServerRequest(Router::METHOD_GET, '/namespace'));

        $this->assertInstanceOf(Route::class, $response);
        $this->assertSame($route, $response);
    }

    /**
     * @return string[]
     */
    public function handleNamespaceData(): array
    {
        return [
            ['Flight\\Routing\\Tests\\Fixtures', '\\BlankController'],
            ['Flight\\Routing\\Tests', '\\Fixtures\\BlankController'],
            ['Flight\\Routing\\Tests', ['\\Fixtures\\BlankController', 'handle']],
        ];
    }

    /**
     * @dataProvider hasResourceData
     *
     * @param mixed $controller
     */
    public function testHandleResource(string $method, $controller): void
    {
        $route = new Route('/user/{id:\d+}', Router::HTTP_METHODS_STANDARD, new ResourceHandler($controller, 'user'));

        $router = Router::withCollection();
        $router->addRoute($route);

        try {
            $response = $router->process(new ServerRequest($method, '/user/23'), new RouteHandler(new Psr17Factory()));

            $this->assertInstanceOf(ResponseInterface::class, $response);
            $this->assertEquals(\strtolower($method) . ' 23', (string) $response->getBody());
        } catch (MethodNotAllowedException $e) {
            $this->assertEquals(
                'Route with "/user/23" path is allowed on request method(s) ' .
                '[GET,POST,PUT,PATCH,DELETE,PURGE,OPTIONS,TRACE,CONNECT], "NONE" is invalid.',
                $e->getMessage()
            );
        } catch (InvalidControllerException $e) {
            $this->assertEquals('Route has an invalid handler type of "array".', $e->getMessage());
        }
    }

    /**
     * @return string[]
     */
    public function hasResourceData(): array
    {
        return [
            [Router::METHOD_GET, new Fixtures\BlankRestful()],
            [Router::METHOD_POST, Fixtures\BlankRestful::class],
            ['NONE', Fixtures\BlankRestful::class],
            [Router::METHOD_DELETE, 'Fixtures\BlankRestful'],
        ];
    }

    /**
     * @dataProvider hasCollectionGroupData
     */
    public function testHandleCollectionGrouping(string $expectedMethod, string $expectedUri): void
    {
        $collector = new RouteCollection();

        $collector->group('api.', function (RouteCollection $group): void {
            $group->get('/', new Fixtures\BlankRequestHandler());
            $group->get('/ping', new Fixtures\BlankRequestHandler());

            $group->group('', function (RouteCollection $group): void {
                $group->prefix('/v1')->domain('https://biurad.com');

                $group->head('/hello/{me}', new Fixtures\BlankRequestHandler())->piped('hello');

                $group->group('_lake_')->head('/ffffffff')->end();
            });
        })->prefix('/api');

        $router = Router::withCollection($collector);
        $router->pipes('hello', $middleware = new Fixtures\BlankMiddleware());

        $response = $router->process(new ServerRequest($expectedMethod, $expectedUri), new RouteHandler(new Psr17Factory()));

        if ('https://biurad.com/api/v1/hello/23' === $expectedUri) {
            $this->assertTrue($middleware->isRunned());
            $this->assertEquals('test', $response->getHeaderLine('Middleware'));
        }

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * @return string[]
     */
    public function hasCollectionGroupData(): array
    {
        return [
            [Router::METHOD_GET, '/api/'],
            [Router::METHOD_GET, '/api/ping'],
            [Router::METHOD_HEAD, 'https://biurad.com/api/v1/hello/23'],
        ];
    }

    /**
     * @return string[]
     */
    public function hasParametersData(): array
    {
        return [
            ['/me', 'My name is Divine with id: me'],
            ['/23', 'My name is Divine with id: 23'],
            ['/none', 'My name is Divine with id: 23'],
        ];
    }
}
