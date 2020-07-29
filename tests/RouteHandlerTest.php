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

use BiuradPHP\Http\Factories\GuzzleHttpPsr7Factory;
use BiuradPHP\Http\Factory\ResponseFactory;
use Flight\Routing\RouteHandler;
use Generator;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

/**
 * RouteHandlerTest
 */
class RouteHandlerTest extends TestCase
{
    public function testConstructor(): void
    {
        $factory = $this->getHandler('res', true);

        $this->assertInstanceOf(RequestHandlerInterface::class, $factory);
    }

    public function testHandle(): void
    {
        $factory  = $this->getHandler('resmsg', true);
        $response = $factory->handle(GuzzleHttpPsr7Factory::fromGlobalRequest());

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals('I am a [GET] method', (string) $response->getBody());
        $this->assertEquals(
            'text/html; charset=utf-8',
            $response->getHeaderLine(RouteHandler::CONTENT_TYPE)
        );
    }

    public function testHandleWithException(): void
    {
        $this->expectException(RuntimeException::class);

        $handler = static function (ServerRequestInterface $request, ResponseInterface $response): void {
            throw new RuntimeException('An error occurred');
        };
        $factory  = new RouteHandler($handler, (new ResponseFactory())->createResponse());
        $factory->handle(GuzzleHttpPsr7Factory::fromGlobalRequest());
    }

    /**
     * @dataProvider implicitHandle
     *
     * @param string $contentType
     * @param mixed  $body
     */
    public function testHandleResponse(string $contentType, $body): void
    {
        $handler  = $this->getHandler($body, true);
        $response = $handler->handle(GuzzleHttpPsr7Factory::fromGlobalRequest());

        if (\is_array($body)) {
            $body = \json_encode($body);
        }

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals($body, (string) $response->getBody());
        $this->assertEquals($contentType, $response->getHeaderLine(RouteHandler::CONTENT_TYPE));
    }

    /**
     * @return Generator
     */
    public function implicitHandle(): Generator
    {
        yield 'Plain Text:' => [
            'text/plain; charset=utf-8',
            'Hello World',
        ];

        yield 'Html Text:' => [
            'text/html; charset=utf-8',
            '<html>Hello World</html>',
        ];

        yield 'Xml Text:' => [
            'application/xml; charset=utf-8',
            '<?xml version="1.0" encoding="UTF-8"?><html>Hello World</html>',
        ];

        yield 'Json Text:' => [
            'application/json',
            ['hello' => 'world'],
        ];
    }

    /**
     * @param mixed $output
     * @param bool  $handler
     *
     * @return callable|RouteHandler
     */
    private function getHandler($output, bool $handler = false)
    {
        $call = static function (ServerRequestInterface $request, ResponseInterface $response) use ($output) {
            if ('resmsg' === $output) {
                $response->getBody()->write(\sprintf('I am a [%s] method', $request->getMethod()));
                $output = 'res';
            }

            return 'res' === $output ? $response : $output;
        };

        if (false !== $handler) {
            return new RouteHandler($call, (new ResponseFactory())->createResponse());
        }

        return $call;
    }
}
