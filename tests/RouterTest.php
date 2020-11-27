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

use DivineNii\Invoker\Exceptions\NotEnoughParametersException;
use DivineNii\Invoker\Invoker;
use Flight\Routing\Exceptions\DuplicateRouteException;
use Flight\Routing\Exceptions\InvalidMiddlewareException;
use Flight\Routing\Exceptions\MethodNotAllowedException;
use Flight\Routing\Exceptions\RouteNotFoundException;
use Flight\Routing\Exceptions\UriHandlerException;
use Flight\Routing\Exceptions\UrlGenerationException;
use Flight\Routing\Interfaces\RouteInterface;
use Flight\Routing\Route;
use Flight\Routing\RouteCollector;
use Flight\Routing\RoutePipeline;
use GuzzleHttp\Psr7\ServerRequest;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * RouterTest
 */
class RouterTest extends BaseTestCase
{
    public function testConstructor(): void
    {
        $router = $this->getRouter();

        $this->assertInstanceOf(RequestHandlerInterface::class, $router);
    }

    public function testAddRoute(): void
    {
        $routes = [
            new Fixtures\TestRoute(),
            new Fixtures\TestRoute(),
            new Fixtures\TestRoute(),
        ];

        $router = $this->getRouter();
        $router->addRoute(...$routes);

        $this->assertSame($routes, $router->getRoutes());
    }

    public function testaddRouteListener(): void
    {
        $router  = $this->getRouter();
        $route   = new Route('phpinfo', [RouteCollector::METHOD_GET], 'phpinfo', 'phpinfo');
        $request = new ServerRequest($route->getMethods()[0], $route->getPath());

        $router->addRoute($route);

        try {
            $router->handle($request);
        } catch (NotEnoughParametersException $e) {
            $this->assertEquals('Unable to invoke the callable because no value was given for parameter 1 ($what)', $e->getMessage());
        }

        $router->addRouteListener(new Fixtures\PhpInfoListener());
        $response = $router->handle($request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals('text/plain; charset=utf-8', $response->getHeaderLine('Content-Type'));
    }

    public function testSetNamespace(): void
    {
        $router = $this->getRouter();
        $router->setNamespace('Flight\\Routing\\Tests');

        $router->addRoute($route = new Route(
            Fixtures\TestRoute::getTestRouteName(),
            [RouteCollector::METHOD_GET],
            Fixtures\TestRoute::getTestRoutePath(),
            '\\Fixtures\\BlankRequestHandler'
        ));

        $request = new ServerRequest($route->getMethods()[0], $route->getPath());
        $handler = $router->match($request);

        $this->assertInstanceOf(RouteInterface::class, $request->getAttribute(Route::class));
        $this->assertInstanceOf(ResponseInterface::class, $handler->handle($request));
    }

    public function testAddExistingRoute(): void
    {
        $route = new Fixtures\TestRoute();

        $router = $this->getRouter();
        $router->addRoute($route);

        $this->expectException(DuplicateRouteException::class);

        $router->addRoute($route);
    }

    public function testGetAllowedMethods(): void
    {
        $routes = [
            new Fixtures\TestRoute(),
            new Fixtures\TestRoute(),
            new Fixtures\TestRoute(),
        ];

        $expectedMethods = \array_merge(
            $routes[0]->getMethods(),
            $routes[1]->getMethods(),
            $routes[2]->getMethods()
        );

        $router = $this->getRouter();

        $this->assertSame([], $router->getAllowedMethods());

        $router->addRoute(...$routes);

        $this->assertSame($expectedMethods, $router->getAllowedMethods());
    }

    public function testGetRoute(): void
    {
        $routes = [
            new Fixtures\TestRoute(),
            new Fixtures\TestRoute(),
            new Fixtures\TestRoute(),
        ];

        $router = $this->getRouter();
        $router->addRoute(...$routes);

        $this->assertSame($routes[1], $router->getRoute($routes[1]->getName()));
    }

    public function testGetUndefinedRoute(): void
    {
        $routes = [
            new Fixtures\TestRoute(),
            new Fixtures\TestRoute(),
            new Fixtures\TestRoute(),
        ];

        $router = $this->getRouter();
        $router->addRoute(...$routes);

        $this->expectExceptionMessage('No route found for the name "foo".');
        $this->expectException(RouteNotFoundException::class);

        $router->getRoute('foo');
    }

    public function testGenerateUri(): void
    {
        $route = new Fixtures\TestRoute();
        $path  = '.' . $route->getPath();

        $router = $this->getRouter($path);
        $router->addRoute($route);

        $this->assertSame($path, (string) $router->generateUri($route->getName()));
    }

    public function testGenerateUriWithQuery(): void
    {
        $route = new Fixtures\TestRoute();
        $path  = '.' . $route->getPath() . '?hello=world&first=1';

        $router = $this->getRouter($path);
        $router->addRoute($route);

        $this->assertSame(
            $path,
            (string) $router->generateUri($route->getName(), [], ['hello' => 'world', 'first' => 1])
        );
    }

    public function testGenerateUriException(): void
    {
        $router = $this->getRouter();

        $this->expectExceptionMessage(
            'Unable to generate a URL for the named route "none" as such route does not exist.'
        );
        $this->expectException(UrlGenerationException::class);

        $router->generateUri('none');
    }

    public function testMatch(): void
    {
        $routes = [
            new Fixtures\TestRoute(),
            new Fixtures\TestRoute(),
            new Fixtures\TestRoute(),
            new Fixtures\TestRoute(),
            new Fixtures\TestRoute(),
        ];

        $router = $this->getRouter();
        $router->addRoute(...$routes);

        $request = new ServerRequest($routes[2]->getMethods()[1], $routes[2]->getPath());
        $router->match($request);

        $this->assertInstanceOf(RouteInterface::class, $request->getAttribute(Route::class));
        $this->assertSame($routes[2]->getName(), $request->getAttribute(Route::class)->getName());
    }

    public function testMatchForUnallowedMethod(): void
    {
        $routes = [
            new Fixtures\TestRoute(),
            new Fixtures\TestRoute(),
            new Fixtures\TestRoute(),
            new Fixtures\TestRoute(),
            new Fixtures\TestRoute(),
        ];

        $routes[2] = new Route(\uniqid(), $routes[2]->getMethods(), $routes[1]->getPath(), 'phpinfo');
        $routes[3] = new Route(\uniqid(), $routes[3]->getMethods(), $routes[1]->getPath(), 'phpinfo');

        $router = $this->getRouter();
        $router->addRoute(...$routes);

        // the given exception message should be tested through exceptions factory...
        $this->expectException(MethodNotAllowedException::class);

        $request = new ServerRequest('GET', $routes[2]->getPath());

        try {
            $router->match($request);
        } catch (MethodNotAllowedException $e) {
            $this->assertSame($routes[1]->getMethods(), $e->getAllowedMethods());

            throw $e;
        }
    }

    public function testMatchForUndefinedRoute(): void
    {
        $routes = [
            new Fixtures\TestRoute(),
            new Fixtures\TestRoute(),
            new Fixtures\TestRoute(),
        ];

        $router = $this->getRouter();
        $router->addRoute(...$routes);

        $this->expectExceptionMessage('Unable to find the controller for path "/". The route is wrongly configured.');
        $this->expectException(RouteNotFoundException::class);

        $request = new ServerRequest($routes[0]->getMethods()[0], '/');
        $router->match($request);
    }

    public function testHandle(): void
    {
        $routes = [
            new Fixtures\TestRoute(),
            new Fixtures\TestRoute(),
            new Fixtures\TestRoute(),
            new Fixtures\TestRoute(),
            new Fixtures\TestRoute(),
        ];

        $router = $this->getRouter();
        $router->addRoute(...$routes);

        $router->handle(new ServerRequest($routes[2]->getMethods()[1], $routes[2]->getPath()));

        $this->assertTrue($routes[2]->getController()->isRunned());
    }

    public function testHandleResponse(): void
    {
        $router = $this->getRouter();
        $router->addRoute($route = new Route(
            Fixtures\TestRoute::getTestRouteName(),
            [RouteCollector::METHOD_GET],
            Fixtures\TestRoute::getTestRoutePath(),
            function (ResponseInterface $response): ResponseInterface {
                $response->getBody()->write('I am a GET method');

                return $response;
            }
        ));

        $response = $router->handle(new ServerRequest(RouteCollector::METHOD_GET, $route->getPath()));

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame('I am a GET method', (string) $response->getBody());
    }

    public function testAddParameters(): void
    {
        $route = new Route(
            'test_id',
            [RouteCollector::METHOD_GET],
            '/{cool}',
            function ($cool, string $name): string {
                return "My name is {$name} with id: {$cool}";
            }
        );

        $router = $this->getRouter();
        $router->addParameters(['cool' => ['23', 'me']]);
        $router->addParameters(['name' => 'Divine'], $router::TYPE_DEFAULT);
        $router->addRoute($route);

        $response = $router->handle(new ServerRequest(RouteCollector::METHOD_GET, '/23'));

        $this->assertSame('My name is Divine with id: 23', (string) $response->getBody());

        $response = $router->handle(new ServerRequest(RouteCollector::METHOD_GET, '/me'));

        $this->assertSame('My name is Divine with id: me', (string) $response->getBody());
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
        $route->addMiddleware(...$middlewares);

        $router = $this->getRouter();
        $router->addRoute($route);
        $response = $router->handle(new ServerRequest(
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
        $this->assertTrue($route->getController()->isRunned());
    }

    public function testHandleMiddlewareWithContainer(): void
    {
        $route = new Fixtures\TestRoute();

        $container = $this->getMockBuilder(ContainerInterface::class)->getMock();
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn(new Fixtures\BlankMiddleware());

        $route->addMiddleware('container');

        $router = $this->getRouter('', null, new Invoker([], $container));
        $router->addRoute($route);

        $response = $router->handle(new ServerRequest($route->getMethods()[0], $route->getPath()));

        $this->assertTrue($response->hasHeader('Middleware'));
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testHandleMultipleMiddlewares(): void
    {
        $route = new Fixtures\TestRoute();

        $middlewares = [[Fixtures\BlankMiddleware::class, Fixtures\BlankRequestHandler::class]];
        $route->addMiddleware(...$middlewares);

        $router = $this->getRouter();
        $router->addRoute($route);

        $response = $router->handle(new ServerRequest($route->getMethods()[0], $route->getPath()));

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
        $route->addMiddleware(...$middlewares);

        $router = $this->getRouter();
        $router->addRoute($route);
        $router->handle(new ServerRequest($route->getMethods()[0], $route->getPath()));

        $this->assertTrue($middlewares[0]->isRunned());
        $this->assertTrue($middlewares[1]->isRunned());
        $this->assertFalse($middlewares[2]->isRunned());
        $this->assertFalse($route->getController()->isRunned());
    }

    public function testHandleInvalidMiddleware(): void
    {
        $route = new Fixtures\TestRoute();
        $route->addMiddleware('none');

        $router = $this->getRouter();
        $router->addRoute($route);

        $this->expectExceptionMessage(
            'Middleware "none" is neither a string service name, a PHP callable, ' .
            'a Psr\Http\Server\MiddlewareInterface instance, a Psr\Http\Server\RequestHandlerInterface instance, ' .
            'or an array of such arguments'
        );
        $this->expectException(InvalidMiddlewareException::class);

        $router->handle(new ServerRequest($route->getMethods()[0], $route->getPath()));
    }

    public function testHandleForUnallowedMethod(): void
    {
        $routes = [
            new Fixtures\TestRoute(),
            new Fixtures\TestRoute(),
            new Fixtures\TestRoute(),
        ];

        $router = $this->getRouter();
        $router->addRoute(...$routes);

        // the given exception message should be tested through exceptions factory...
        $this->expectException(MethodNotAllowedException::class);

        try {
            $router->handle(new ServerRequest('GET', $routes[1]->getPath()));
        } catch (MethodNotAllowedException $e) {
            $allowedMethods = $routes[1]->getMethods();

            $this->assertSame($allowedMethods, $e->getAllowedMethods());

            throw $e;
        }
    }

    public function testHandleForUndefinedRoute(): void
    {
        $routes = [
            new Fixtures\TestRoute(),
            new Fixtures\TestRoute(),
            new Fixtures\TestRoute(),
        ];

        $router = $this->getRouter();
        $router->addRoute(...$routes);

        $this->expectExceptionMessage('Unable to find the controller for path "/". The route is wrongly configured.');
        $this->expectException(RouteNotFoundException::class);

        $router->handle(new ServerRequest($routes[0]->getMethods()[0], '/'));
    }

    public function testHandleForUndefinedScheme(): void
    {
        $routes = [
            new Fixtures\TestRoute(),
            new Fixtures\TestRoute(),
            new Fixtures\TestRoute(),
        ];

        $routes[0]->setScheme('ftp');

        $router = $this->getRouter();
        $router->addRoute(...$routes);

        $this->expectExceptionMessage(\sprintf(
            'Unfortunately current scheme "http" is not allowed on requested uri [%s]',
            $routes[0]->getPath()
        ));
        $this->expectException(UriHandlerException::class);

        $router->handle(new ServerRequest(
            $routes[0]->getMethods()[0],
            'http://localhost.com' . $routes[0]->getPath()
        ));
    }

    public function testHandleForUndefinedDomain(): void
    {
        $routes = [
            new Fixtures\TestRoute(),
            new Fixtures\TestRoute(),
            new Fixtures\TestRoute(),
        ];

        $routes[0]->setDomain('biurad.com');

        $router = $this->getRouter();
        $router->addRoute(...$routes);

        $this->expectExceptionMessage(\sprintf(
            'Unfortunately current domain "localhost.com" is not allowed on requested uri [%s]',
            $routes[0]->getPath()
        ));
        $this->expectException(UriHandlerException::class);

        $router->handle(new ServerRequest(
            $routes[0]->getMethods()[0],
            'http://localhost.com' . $routes[0]->getPath()
        ));
    }

    public function testHandleWithMiddlewareException(): void
    {
        $route = new Route(
            'test_middleware',
            [RouteCollector::METHOD_GET],
            '/middleware',
            Fixtures\BlankRequestHandler::class
        );
        $route->addMiddleware('none');

        ($router = $this->getRouter())->addRoute($route);
        $pipeline = (new RoutePipeline())->withHandler($router);

        $this->expectExceptionMessage(
            'Middleware "none" is neither a string service name, ' .
            'a PHP callable, a Psr\Http\Server\MiddlewareInterface instance, ' .
            'a Psr\Http\Server\RequestHandlerInterface instance, or an array of such arguments'
        );
        $this->expectException(InvalidMiddlewareException::class);

        $pipeline->handle(new ServerRequest(RouteCollector::METHOD_GET, '/middleware'));
    }

    /**
     * @dataProvider handleNamespaceData
     *
     * @param string          $namespace
     * @param string|string[] $controller
     */
    public function testHandleWithNamespace(string $namespace, $controller): void
    {
        $route = new Route(
            'test_namespace',
            [RouteCollector::METHOD_GET],
            '/namespace',
            $controller
        );

        $router = $this->getRouter();
        $router->setNamespace($namespace);
        $router->addRoute($route);

        $response = $router->handle(new ServerRequest(RouteCollector::METHOD_GET, '/namespace'));

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    /**
     * @return string[]
     */
    public function handleNamespaceData(): array
    {
        return [
            ['Flight\\Routing\\Tests\\Fixtures\\', 'BlankController'],
            ['Flight\\Routing\\Tests', '\\Fixtures\\BlankController'],
            ['Flight\\Routing\\Tests\\', ['Fixtures\\BlankController', 'handle']],
        ];
    }

    /**
     * @dataProvider hasResourceData
     *
     * @param string $name
     * @param string $method
     * @param mixed  $controller
     */
    public function testHandleResource(string $name, string $method, $controller): void
    {
        $route = new Route($name, RouteCollector::HTTP_METHODS_STANDARD, '/user/{id:\d+}', $controller);

        $router = $this->getRouter();
        $router->addRoute($route);
        $response = $router->handle(new ServerRequest($method, 'user/23'));

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(\strtolower($method) . ' 23', (string) $response->getBody());
    }

    /**
     * @return string[]
     */
    public function hasResourceData(): array
    {
        $controller = [new Fixtures\BlankRestful(), 'user'];

        return [
            ['named__restful', RouteCollector::METHOD_GET, $controller],
            ['user__restful', RouteCollector::METHOD_POST, Fixtures\BlankRestful::class],
            ['another__restful', RouteCollector::METHOD_DELETE, $controller],
        ];
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
            ->addDomain('https://biurad.com');
        })->addPrefix('/api')->setName('api.');

        $router = $this->getRouter();
        $router->addRoute(...$collector->getCollection());

        $response = $router->handle(new ServerRequest($expectedMethod, $expectedUri));

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
