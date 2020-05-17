<?php

declare(strict_types=1);

/*
 * This code is under BSD 3-Clause "New" or "Revised" License.
 *
 * PHP version 7 and above required
 *
 * @category  RoutingManager
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 Biurad Group (https://biurad.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * @link      https://www.biurad.com/projects/routingmanager
 * @since     Version 0.1
 */

namespace Flight\Routing\Tests;

use Flight\Routing\Concerns\CallableResolver;
use Flight\Routing\Concerns\HttpMethods;
use Flight\Routing\Exceptions\MethodNotAllowedException;
use Flight\Routing\Exceptions\RouteNotFoundException;
use Flight\Routing\Interfaces\RouteCollectorInterface;
use Flight\Routing\Interfaces\RouteGroupInterface;
use Flight\Routing\Interfaces\RouteInterface;
use Flight\Routing\Interfaces\RouterInterface;
use Flight\Routing\Interfaces\RouterProxyInterface;
use Flight\Routing\Route;
use Flight\Routing\RouteCollector;
use Flight\Routing\RouteResults;
use Flight\Routing\Tests\Fixtures\SampleController;
use Flight\Routing\Tests\Fixtures\SampleMiddleware;
use Generator;
use function implode;
use Laminas\Stratigility\Next;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Base class for testing adapter integrations.
 *
 * Implementers of adapters should extend this class in their test suite,
 * implementing the `getRouter()` method.
 *
 * This test class tests that the router correctly marshals the allowed methods
 * for a match that matches the path, but not the request method.
 */
abstract class RouterIntegrationTest extends TestCase
{
    abstract public function getRouter(): RouterInterface;

    abstract public function psrServerResponseFactory(): array;

    public function getRouteCollection(ContainerInterface $container = null): RouteCollectorInterface
    {
        [$serverRequest, $responseFactory] = $this->psrServerResponseFactory();

        return new RouteCollector($serverRequest, $responseFactory, $this->getRouter(), null, $container);
    }

    public function createInvalidResponseFactory(): callable
    {
        return function () {
            Assert::fail('Response generated when it should not have been');
        };
    }

    public function method(): Generator
    {
        yield 'HEAD: head, post' => [
            HttpMethods::METHOD_HEAD,
            [HttpMethods::METHOD_HEAD, HttpMethods::METHOD_POST],
        ];

        yield 'HEAD: head, get' => [
            HttpMethods::METHOD_HEAD,
            [HttpMethods::METHOD_HEAD, HttpMethods::METHOD_GET],
        ];

        yield 'HEAD: post, head' => [
            HttpMethods::METHOD_HEAD,
            [HttpMethods::METHOD_POST, HttpMethods::METHOD_HEAD],
        ];

        yield 'HEAD: get, head' => [
            HttpMethods::METHOD_HEAD,
            [HttpMethods::METHOD_GET, HttpMethods::METHOD_HEAD],
        ];

        yield 'OPTIONS: options, post' => [
            HttpMethods::METHOD_OPTIONS,
            [HttpMethods::METHOD_OPTIONS, HttpMethods::METHOD_POST],
        ];

        yield 'OPTIONS: options, get' => [
            HttpMethods::METHOD_OPTIONS,
            [HttpMethods::METHOD_OPTIONS, HttpMethods::METHOD_GET],
        ];

        yield 'OPTIONS: post, options' => [
            HttpMethods::METHOD_OPTIONS,
            [HttpMethods::METHOD_POST, HttpMethods::METHOD_OPTIONS],
        ];

        yield 'OPTIONS: get, options' => [
            HttpMethods::METHOD_OPTIONS,
            [HttpMethods::METHOD_GET, HttpMethods::METHOD_OPTIONS],
        ];
    }

    /**
     * @dataProvider method
     */
    public function testExplicitRequest(string $method, array $routes)
    {
        $router = $this->getRouteCollection();
        [$serverRequest, $responseFactory] = $this->psrServerResponseFactory();

        $finalResponse = (new $responseFactory())->createResponse();
        $finalResponse = $finalResponse->withHeader('foo-bar', 'baz');
        $finalResponse->getBody()->write('FOO BAR BODY');

        $finalHandler = $this->prophesize(RequestHandlerInterface::class);
        $finalHandler
            ->handle(Argument::that(function (ServerRequestInterface $request) use ($method) {
                Assert::assertSame($method, $request->getMethod());

                $routeResult = $request->getAttribute(RouteResults::class);
                Assert::assertInstanceOf(RouteResults::class, $routeResult);
                Assert::assertTrue(RouteResults::FOUND === $routeResult->getROuteStatus());

                $matchedRoute = $routeResult->getMatchedRoute();
                Assert::assertNotFalse($matchedRoute);

                return true;
            }))
            ->willReturn($finalResponse)
            ->shouldBeCalledTimes(1);

        foreach ($routes as $routeMethod) {
            $route = new Route(
                [$routeMethod],
                '/api/v1/me',
                $finalHandler->reveal(),
                [$responseFactory, 'createResponse'],
                new CallableResolver()
            );

            if ($routeMethod === $method) {
                $router->setRoute($route);
            }
        }

        $path = $serverRequest->getUri()->withPath('/api/v1/me');
        $router->setRequest($serverRequest->withMethod($method)->withUri($path));
        $response = $router->dispatch();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame('FOO BAR BODY', (string) $response->getBody());
        $this->assertTrue($response->hasHeader('foo-bar'));
        $this->assertSame('baz', $response->getHeaderLine('foo-bar'));
    }

    public function withoutImplicitMiddleware()
    {
        // @codingStandardsIgnoreStart
        // request method, array of allowed methods for a route.
        yield 'HEAD: get'          => [HttpMethods::METHOD_HEAD, [HttpMethods::METHOD_GET]];
        yield 'HEAD: post'         => [HttpMethods::METHOD_HEAD, [HttpMethods::METHOD_POST]];
        yield 'HEAD: get, post'    => [HttpMethods::METHOD_HEAD, [HttpMethods::METHOD_GET, HttpMethods::METHOD_POST]];

        yield 'OPTIONS: get'       => [HttpMethods::METHOD_OPTIONS, [HttpMethods::METHOD_GET]];
        yield 'OPTIONS: post'      => [HttpMethods::METHOD_OPTIONS, [HttpMethods::METHOD_POST]];
        yield 'OPTIONS: get, post' => [HttpMethods::METHOD_OPTIONS, [HttpMethods::METHOD_GET, HttpMethods::METHOD_POST]];
        // @codingStandardsIgnoreEnd
    }

    /**
     * In case we are not using Implicit*Middlewares and we don't have any route with explicit method
     * returned response should be 405: Method Not Allowed - handled by MethodNotAllowedMiddleware.
     *
     * @dataProvider withoutImplicitMiddleware
     */
    public function testWithoutImplicitMiddleware(string $requestMethod, array $allowedMethods)
    {
        $router = $this->getRouteCollection();
        [$serverRequest, $responseFactory] = $this->psrServerResponseFactory();

        $finalResponse = $this->prophesize(ResponseInterface::class);
        $finalResponse->withStatus(405)->will([$finalResponse, 'reveal']);
        $finalResponse->withHeader('Allow', implode(',', $allowedMethods))->will([$finalResponse, 'reveal']);

        $finalHandler = $this->prophesize(RequestHandlerInterface::class);
        $finalHandler->handle(Argument::any())->shouldNotBeCalled();

        foreach ($allowedMethods as $routeMethod) {
            $route = (new Route(
                [$routeMethod],
                '/api/v1/me',
                $finalHandler->reveal(),
                [$responseFactory, 'createResponse'],
                new CallableResolver()
            ))->addMiddleware(function (ServerRequestInterface $request, RequestHandlerInterface $handler) {
                return $handler->handle($request);
            });

            $router->setRoute($route);
        }

        $this->expectException(MethodNotAllowedException::class);

        $path = $serverRequest->getUri()->withPath('/api/v1/me');
        $router->setRequest($serverRequest->withMethod($requestMethod)->withUri($path));
        $router->dispatch();
    }

    /**
     * Provider for the testImplicitHeadRequest method.
     *
     * Implementations must provide this method. Each test case returned
     * must consist of the following three elements, in order:
     *
     * - string route path (the match string)
     * - string request path (the path in the ServerRequest instance)
     * - string request method (for ServerRequest instance)
     * - callable|object|string|null, returning route's response
     * - array route options (if any/required): selected keys:
     *   - [
     *        name => {subject},
     *        domain => {host}, (for ServerRequest instance)
     *        scheme => {scheme}, (for ServerRequest instance)
     *  	  scheme => {scheme},
     *        middlewares => [...],
     *        regex => [{param} => {pattern}...],
     *        defaults => [{$key} => {$value}...]
     *     ]
     * - array of asserts options (if any/required): selected keys:
     *   - [
     *        body => {contents},
     *        status => {code},
     *        content-type => {content-type},
     *        header => {header}
     *     ]
     */
    abstract public function implicitRoutesAndRequests(): Generator;

    /**
     * @dataProvider implicitRoutesAndRequests
     */
    public function testWithImplicitMiddleware(string $routePath, string $requestPath, string $requestMethod, $controller, array $routeOptions = [], array $asserts = [])
    {
        $router = $this->getRouteCollection();
        [$serverRequest, $responseFactory] = $this->psrServerResponseFactory();

        $finalResponse = (new $responseFactory())->createResponse();
        $finalResponse = $finalResponse->withHeader('foo-bar', 'baz');

        $middleware = $this->prophesize(MiddlewareInterface::class);
        $middleware
            ->process(Argument::type(ServerRequestInterface::class), Argument::type(RequestHandlerInterface::class))
            ->willReturn($finalResponse)
            ->shouldBeCalledTimes(1);

        $router->map([$requestMethod], $routePath, function () use ($finalResponse) {
            return $finalResponse;
        })->addMiddleware(function (ServerRequestInterface $request, RequestHandlerInterface $handler) use ($middleware, $requestMethod) {
            Assert::assertEquals($requestMethod, $request->getMethod());
            Assert::assertInstanceOf(Next::class, $handler);

            return $middleware->reveal()->process($request, $handler);
        })->addDomain($routeOptions['domain'] ?? '')
        ->setName($routeOptions['name'] ?? null)
        ->whereArray($routeOptions['regex'] ?? [])
        ->addDefaults($routeOptions['defaults'] ?? [])
        ->addSchemes($routeOptions['scheme'] ?? null);

        $path = $serverRequest->getUri()->withPath($requestPath);
        if (isset($routeOptions['domain'])) {
            $path = $path->withHost($routeOptions['domain']);
        }
        if (isset($routeOptions['scheme'])) {
            $path = $path->withScheme($routeOptions['scheme']);
        }

        $router->setRequest($serverRequest->withMethod($requestMethod)->withUri($path));
        $response = $router->dispatch();

        $this->assertSame($finalResponse, $response);
        $this->assertEquals('baz', $response->getHeaderLine('foo-bar'));
    }

    /**
     * @dataProvider implicitRoutesAndRequests
     */
    public function testWithImplicitRouteMatch(string $routePath, string $requestPath, string $requestMethod, $controller, array $routeOptions = [], array $asserts = [])
    {
        $router = $this->getRouteCollection();
        [$serverRequest,] = $this->psrServerResponseFactory();

        $router->map([$requestMethod], $routePath, $controller)
        ->setName($routeOptions['name'] ?? null)
        ->addMiddleware($routeOptions['middlewares'] ?? [])
        ->whereArray($routeOptions['regex'] ?? [])
        ->addDefaults($routeOptions['defaults'] ?? [])
        ->addSchemes($routeOptions['scheme'] ?? null);

        $path = $serverRequest->getUri()->withPath($requestPath);
        if (isset($routeOptions['domain'])) {
            $path = $path->withHost($routeOptions['domain']);
        }
        if (isset($routeOptions['scheme'])) {
            $path = $path->withScheme($routeOptions['scheme']);
        }

        $router->setRequest($serverRequest->withMethod($requestMethod)->withUri($path));
        $response = $router->dispatch();

        if (isset($asserts['status'])) {
            $this->assertSame($asserts['status'], $response->getStatusCode());
        }
        if (isset($asserts['body'])) {
            $this->assertEquals($asserts['body'], (string) $response->getBody());
        }
        if (isset($asserts['content-type'])) {
            $this->assertEquals($asserts['content-type'], $response->getHeaderLine('Content-Type'));
        }
        if (isset($asserts['header'])) {
            $this->assertTrue($response->hasHeader($asserts['header']));
        }

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertInstanceOf(RouteInterface::class, $router->currentRoute());
    }

    public function testWithImplicitRouteGroup()
    {
        $router = $this->getRouteCollection();
        [$serverRequest,] = $this->psrServerResponseFactory();
        $path = $serverRequest->getUri()->withPath('/group/test');

        $router->group([
            RouteGroupInterface::NAME         => 'group',
            RouteGroupInterface::PREFIX       => 'group',
            RouteGroupInterface::REQUIREMENTS => [],
            RouteGroupInterface::DEFAULTS     => ['how' => 'What to do?'],
            RouteGroupInterface::MIDDLEWARES  => [SampleMiddleware::class],
            RouteGroupInterface::SCHEMES      => null,
        ], function (RouterProxyInterface $route): void {
            $route->get('/test*<homePageRequestString>', SampleController::class)->setName('_hello');
        });

        $router->setRequest($serverRequest->withMethod(HttpMethods::METHOD_GET)->withUri($path));
        $router->dispatch();

        $this->assertInstanceOf(RouteInterface::class, $route = $router->currentRoute());
        $this->assertTrue($route->hasDefault('how'));
        $this->assertEquals('group_hello', $route->getName());
        $this->assertTrue(in_array(SampleMiddleware::class, $route->getMiddlewares(), true));
        $this->assertTrue($route->hasGroup());
        $this->assertEquals('group/test', $route->getPath());
    }

    public function testImplicitRouteNotFound()
    {
        $router = $this->getRouteCollection();
        [$serverRequest,] = $this->psrServerResponseFactory();

        $this->expectException(RouteNotFoundException::class);

        $path = $serverRequest->getUri()->withPath('/not-found');
        $router->setRequest($serverRequest->withMethod(HttpMethods::METHOD_GET)->withUri($path));
        $router->dispatch();
    }
}
