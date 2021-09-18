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

use Flight\Routing\Exceptions\InvalidControllerException;
use Flight\Routing\Exceptions\RouteNotFoundException;
use Flight\Routing\Handlers\CallbackHandler;
use Flight\Routing\Handlers\RouteHandler;
use Flight\Routing\Routes\FastRoute;
use Flight\Routing\Routes\Route;
use Laminas\Stratigility\MiddlewarePipe;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * RouteHandlerTest.
 */
class RouteHandlerTest extends TestCase
{
    public function testConstructor(): void
    {
        $factory = $this->getHandler('res', true);

        $this->assertInstanceOf(RequestHandlerInterface::class, $factory);
    }

    public function testRouteNotFound(): void
    {
        $this->expectExceptionMessage('Unable to find the controller for path "/bar". The route is wrongly configured.');
        $this->expectException(RouteNotFoundException::class);

        $factory = new RouteHandler(new Psr17Factory());
        $factory->handle($this->serverCreator());
    }

    public function testOverrideRouteNotFound(): void
    {
        $request = $this->serverCreator();
        $handler = new RouteHandler(new Psr17Factory());

        $pipe1 = (new MiddlewarePipe())->process($request->withAttribute(RouteHandler::OVERRIDE_HTTP_RESPONSE, true), $handler);
        $pipe2 = (new MiddlewarePipe())->process($request->withAttribute(RouteHandler::OVERRIDE_HTTP_RESPONSE, $pipe1), $handler);

        $this->assertEquals($pipe1, $pipe2);
    }

    public function testHandle(): void
    {
        $factory = $this->getHandler('resmsg', true);
        $response = $factory->handle($this->serverCreator());

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals('I am a [GET] method', (string) $response->getBody());
        $this->assertEquals(
            'text/html; charset=utf-8',
            $response->getHeaderLine('Content-Type')
        );
    }

    public function testHandleWithException(): void
    {
        $this->expectException(\RuntimeException::class);

        $handler = static function (ServerRequestInterface $request): ResponseInterface {
            throw new \RuntimeException('An error occurred');
        };

        $route = new Route('/bar', Route::DEFAULT_METHODS, $handler);
        $factory = new RouteHandler(new Psr17Factory());
        $factory->handle($this->serverCreator()->withAttribute(FastRoute::class, $route));
    }

    /**
     * @dataProvider implicitHandle
     *
     * @param mixed $body
     */
    public function testHandleResponse(string $contentType, $body): void
    {
        if (\is_array($body)) {
            $body = \json_encode($body);
        }

        $handler = $this->getHandler(new Response(200, [], $body), true);
        $response = $handler->handle($this->serverCreator());

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals($body, (string) $response->getBody());
        $this->assertEquals($contentType, $response->getHeaderLine('Content-Type'));
    }

    public function testEchoHandleResponse(): void
    {
        $call = static function (ServerRequestInterface $request, ResponseFactoryInterface $response): void {
            echo 'Hello World To Flight Routing';
        };

        $route = new Route('/bar', Route::DEFAULT_METHODS, $call);
        $response = (new RouteHandler(new Psr17Factory()))
             ->handle($this->serverCreator()->withAttribute(FastRoute::class, $route));

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals('Hello World To Flight Routing', (string) $response->getBody());
    }

    public function testInvalidRouteHandler(): void
    {
        $this->expectExceptionMessage('Route has an invalid handler type of "NULL".');
        $this->expectException(InvalidControllerException::class);

        $route = new Route('/bar', Route::DEFAULT_METHODS);
        (new RouteHandler(new Psr17Factory()))->handle($this->serverCreator()->withAttribute(FastRoute::class, $route));
    }

    public function testInvalidHandlerResponse(): void
    {
        $this->expectExceptionMessage('The route handler\'s content is not a valid PSR7 response body stream.');
        $this->expectException(InvalidControllerException::class);

        $call = static function (): bool {
            return false;
        };
        $route = new Route('/bar', Route::DEFAULT_METHODS, $call);
        (new RouteHandler(new Psr17Factory()))->handle($this->serverCreator()->withAttribute(FastRoute::class, $route));
    }

    public function implicitHandle(): \Generator
    {
        yield 'Plain Text:' => [
            'text/plain; charset=utf-8',
            'Hello World',
        ];

        yield 'Html Text:' => [
            'text/html; charset=utf-8',
            '<html>Hello World</html>',
        ];

        yield 'Xml Text as:' => [
            'application/xml; charset=utf-8',
            '<?xml version="1.0" encoding="UTF-8"?><html>Hello World</html>',
        ];

        yield 'Rss Text as:' => [
            'application/rss+xml; charset=utf-8',
            '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0" xmlns:slash="http://purl.org/rss/1.0/modules/slash/"></rss>',
        ];

        yield 'Svg Text:' => [
            'image/svg+xml',
            '<?xml version="1.0" encoding="UTF-8"?><svg><metadata>Hello World</metadata></svg>',
        ];

        yield 'Json Text:' => [
            'application/json',
            ['hello' => 'world'],
        ];
    }

    /**
     * @param mixed $output
     */
    private function getHandler($output, bool $hasResponse = false): CallbackHandler
    {
        $response = (new Psr17Factory())->createResponse()->withHeader('Content-Type', 'text/html; charset=utf-8');

        $call = static function (ServerRequestInterface $request) use ($response, $output) {
            if ('resmsg' === $output) {
                $response->getBody()->write(\sprintf('I am a [%s] method', $request->getMethod()));
                $output = 'res';
            }

            return 'res' === $output ? $response : $output;
        };

        if ($hasResponse) {
            $call = static function (ServerRequestInterface $request) use ($call): ResponseInterface {
                return (new RouteHandler(new Psr17Factory()))
                    ->handle($request->withAttribute(FastRoute::class, new Route('/bar', Route::DEFAULT_METHODS, $call)));
            };
        }

        return new CallbackHandler($call);
    }

    private function serverCreator(): ServerRequestInterface
    {
        return new ServerRequest('GET', '/bar');
    }
}
