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

use Flight\Routing\Handlers\RouteInvoker;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class RouteInvokerTest extends TestCase
{
    public function testConstructor(): void
    {
        $invoker = new RouteInvoker();
        $this->assertIsCallable($invoker);
    }

    /**
     * @dataProvider handlerProvider
     *
     * @param mixed                   $handler
     * @param array<int|string,mixed> $arguments
     * @param mixed                   $expect
     */
    public function testHandler($handler, array $arguments, $expect): void
    {
        $invoker = new RouteInvoker();
        $response = $invoker($handler, $arguments + [ServerRequestInterface::class => new ServerRequest('GET', '/foo')]);

        if (\is_string($expect) && (\class_exists($expect) || \interface_exists($expect))) {
            $this->assertInstanceOf($expect, $response);
        } else {
            $this->assertEquals($expect, $response);
        }
    }

    public function handlerProvider(): \Generator
    {
        // [handler, arguments, expect]
        yield 'closure callable with named parameter' => [
            static function (string $name): string {
                return $name;
            },
            ['name' => 'Flight Routing'],
            'Flight Routing',
        ];

        yield 'closure callable with mixed parameter' => [
            static function ($name) {
                return $name;
            },
            [],
            null,
        ];

        yield 'closure callable with mixed parameter and default' => [
            static function ($name = 'Flight') {
                return $name;
            },
            [],
            'Flight',
        ];

        yield 'class object closure' => [
            new Fixtures\InvokeController(),
            [],
            ResponseInterface::class,
        ];

        yield 'class string closure' => [
            Fixtures\InvokeController::class,
            [],
            ResponseInterface::class,
        ];

        yield 'callable with a @ separator' => [
            Fixtures\BlankController::class . '@handle',
            [],
            ResponseInterface::class,
        ];

        yield 'class object' => [
            new Fixtures\BlankRequestHandler(),
            [],
            Fixtures\BlankRequestHandler::class,
        ];

        yield 'class string' => [
            Fixtures\BlankController::class,
            [],
            Fixtures\BlankController::class,
        ];
    }
}
