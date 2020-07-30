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

use BiuradPHP\Http\Factory\ServerRequestFactory;
use BiuradPHP\Http\Response;
use Flight\Routing\Exceptions\DuplicateRouteException;
use Flight\Routing\Exceptions\MethodNotAllowedException;
use Flight\Routing\Exceptions\RouteNotFoundException;
use Flight\Routing\Exceptions\UrlGenerationException;
use Flight\Routing\Interfaces\RouteInterface;
use Flight\Routing\Route;
use Flight\Routing\Router;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * RouterTest
 */
class RouterTest extends TestCase
{
    public function getRouter(): Router
    {
        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->method('createResponse')
            ->willReturn(new Response());

        return new Router($responseFactory);
    }

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

    public function testAddMiddleware(): void
    {
        $middlewares = [
            new Fixtures\BlankMiddleware(),
            new Fixtures\BlankMiddleware(),
            new Fixtures\BlankMiddleware(),
        ];

        $router = $this->getRouter();
        $router->addMiddleware(...$middlewares);

        $this->assertSame($middlewares, $router->getMiddlewares());
    }

    public function testAddExistingRoute(): void
    {
        $route = new Fixtures\TestRoute();

        $router = $this->getRouter();
        $router->addRoute($route);

        // the given exception message should be tested through exceptions factory...
        $this->expectException(DuplicateRouteException::class);

        $router->addRoute($route);
    }

    public function testAddExistingMiddleware(): void
    {
        $middleware = new Fixtures\BlankMiddleware();

        $router = $this->getRouter();
        $router->addMiddleware($middleware);

        // the given exception message should be tested through exceptions factory...
        $this->expectException(DuplicateRouteException::class);

        $router->addMiddleware($middleware);
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

        // the given exception message should be tested through exceptions factory...
        $this->expectException(RouteNotFoundException::class);

        $router->getRoute('foo');
    }

    public function testGenerateUri(): void
    {
        $route = new Fixtures\TestRoute();

        $router = $this->getRouter();
        $router->addRoute($route);

        $this->assertSame('.' . $route->getPath(), $router->generateUri($route->getName()));
    }

    public function testGenerateUriWithQuery(): void
    {
        $route = new Fixtures\TestRoute();

        $router = $this->getRouter();
        $router->addRoute($route);

        $this->assertSame(
            '.' . $route->getPath() . '?hello=world&first=1',
            $router->generateUri($route->getName(), [], ['hello' => 'world', 'first' => 1])
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

        $request = (new ServerRequestFactory())
            ->createServerRequest(
                $routes[2]->getMethods()[1],
                $routes[2]->getPath()
            );
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

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', $routes[2]->getPath());

        // the given exception message should be tested through exceptions factory...
        $this->expectException(MethodNotAllowedException::class);

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

        $request = (new ServerRequestFactory())
            ->createServerRequest($routes[0]->getMethods()[0], '/');

        // the given exception message should be tested through exceptions factory...
        $this->expectException(RouteNotFoundException::class);

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

        $router->handle((new ServerRequestFactory())
            ->createServerRequest(
                $routes[2]->getMethods()[1],
                $routes[2]->getPath()
            ));

        $this->assertTrue($routes[2]->getController()->isRunned());
    }

    public function testHandleWithMiddlewares(): void
    {
        $route = new Fixtures\TestRoute();

        $middlewares = [
            new Fixtures\BlankMiddleware(),
            new Fixtures\BlankMiddleware(),
            new Fixtures\BlankMiddleware(),
        ];

        $router = $this->getRouter();
        $router->addRoute($route);
        $router->addMiddleware(...$middlewares);
        $router->handle((new ServerRequestFactory())
            ->createServerRequest(
                $route->getMethods()[0],
                $route->getPath()
            ));

        $this->assertTrue($middlewares[0]->isRunned());
        $this->assertTrue($middlewares[1]->isRunned());
        $this->assertTrue($middlewares[2]->isRunned());
        $this->assertTrue($route->getController()->isRunned());
    }

    public function testHandleWithBrokenMiddleware(): void
    {
        $route = new Fixtures\TestRoute();

        $middlewares = [
            new Fixtures\BlankMiddleware(),
            new Fixtures\BlankMiddleware(true),
            new Fixtures\BlankMiddleware(),
        ];

        $router = $this->getRouter();
        $router->addRoute($route);
        $router->addMiddleware(...$middlewares);
        $router->handle((new ServerRequestFactory())
            ->createServerRequest(
                $route->getMethods()[0],
                $route->getPath()
            ));

        $this->assertTrue($middlewares[0]->isRunned());
        $this->assertTrue($middlewares[1]->isRunned());
        $this->assertFalse($middlewares[2]->isRunned());
        $this->assertFalse($route->getController()->isRunned());
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

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', $routes[1]->getPath());

        // the given exception message should be tested through exceptions factory...
        $this->expectException(MethodNotAllowedException::class);

        try {
            $router->handle($request);
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

        $request = (new ServerRequestFactory())
            ->createServerRequest($routes[0]->getMethods()[0], '/');

        // the given exception message should be tested through exceptions factory...
        $this->expectException(RouteNotFoundException::class);

        $router->handle($request);
    }
}
