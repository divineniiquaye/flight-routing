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

use Flight\Routing\CallableResolver;
use Flight\Routing\Exceptions\InvalidControllerException;
use Flight\Routing\Interfaces\CallableResolverInterface;
use Flight\Routing\Interfaces\RouteInterface;
use Flight\Routing\Route;
use Generator;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * CallableResolverTest
 */
class CallableResolverTest extends TestCase
{
    public function testConstructor(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $factory   = new CallableResolver($container);

        $this->assertInstanceOf(CallableResolverInterface::class, $factory);
    }

    /**
     * @dataProvider implicitTypes
     *
     * @param mixed $unResolved
     */
    public function testResolve($unResolved): void
    {
        $factory = new CallableResolver();

        $this->assertIsCallable($factory->resolve($unResolved));
    }

    public function testResolveWithContainer(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->with('handler')->willReturn(true);
        $container->method('get')->willReturn(new Fixtures\BlankRequestHandler());

        $factory = new CallableResolver($container);

        $this->assertIsCallable($factory->resolve('handler'));
        $this->assertIsCallable($factory->resolve(['handler', 'handle']));
    }

    public function testResolveWithInstanceToClosure(): void
    {
        $callable = function () {
            return $this;
        };

        $factory = new CallableResolver();
        $factory->addInstanceToClosure(new Route('test', ['GET'], '/test', 'phpinfo'));

        $this->assertIsCallable($callable = $factory->resolve($callable));
        $this->assertInstanceOf(RouteInterface::class, ($callable)());
    }

    public function testResolveWithNamespace(): void
    {
        $factory = new CallableResolver();

        $callable1 = $factory->resolve('BlankController', 'Flight\\Routing\\Tests\\Fixtures\\');
        $callable2 = $factory->resolve('\\Fixtures\\BlankController', 'Flight\\Routing\\Tests');
        $callable3 = $factory->resolve(['Fixtures\\BlankController', 'handle'], 'Flight\\Routing\\Tests\\');

        $this->assertIsCallable($callable1);
        $this->assertIsCallable($callable2);
        $this->assertIsCallable($callable3);
    }

    public function testNotResolveWithArray(): void
    {
        $factory = new CallableResolver();

        $this->expectExceptionMessage('Controller could not be resolved as callable');
        $this->expectException(InvalidControllerException::class);

        $factory->resolve([Fixtures\BlankController::class, 'none']);
    }

    public function testNotResolveWithString(): void
    {
        $factory = new CallableResolver();

        $this->expectExceptionMessage('"handler" is not resolvable');
        $this->expectException(InvalidControllerException::class);

        $factory->resolve('handler');
    }

    /**
     * @return Generator
     */
    public function implicitTypes(): Generator
    {
        yield 'Object Class Type:' => [
            new Fixtures\BlankRequestHandler(),
        ];

        yield 'String Invocable Class Type:' => [
            Fixtures\BlankController::class,
        ];

        yield 'Object Invocable Class Type:' => [
            new Fixtures\BlankController(),
        ];

        yield 'Callable Class Type:' => [
            [new Fixtures\BlankController(), 'handle'],
        ];

        yield 'Array Class Type:' => [
            [Fixtures\BlankController::class, 'handle'],
        ];

        yield 'Callable RequestHandler Class Type:' => [
            [new Fixtures\BlankRequestHandler(), 'handle'],
        ];

        yield 'Array RequestHandler Class Type:' => [
            [Fixtures\BlankRequestHandler::class, 'handle'],
        ];

        yield 'Pattern : Class Type:' => [
            'Flight\Routing\Tests\Fixtures\BlankRequestHandler:handle',
        ];

        yield 'Pattern @ Class Type:' => [
            'Flight\Routing\Tests\Fixtures\BlankRequestHandler@handle',
        ];

        yield 'Callable String Type:' => [
            'phpinfo',
        ];

        yield 'Callable Closure Type:' => [
            function (string $something): string {
                return $something;
            },
        ];

        yield 'Callable Static String Class Type 1' => [
            'Flight\Routing\Tests\Fixtures\BlankController::process',
        ];
    }
}
