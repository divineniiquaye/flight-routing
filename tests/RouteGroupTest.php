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

use Flight\Routing\Interfaces\RouteCollectorInterface;
use Flight\Routing\Route;
use Flight\Routing\RouteCollection;
use Flight\Routing\RouteGroup;
use PHPUnit\Framework\TestCase;

/**
 * RouteGroupTest
 */
class RouteGroupTest extends TestCase
{
    public function testSetName(): void
    {
        $routes = [
            new Fixtures\TestRoute(),
            new Fixtures\TestRoute(),
            new Fixtures\TestRoute(),
        ];

        $routes[0]->setName('foo');
        $routes[1]->setName('bar');
        $routes[2]->setName('baz');

        $collection = new RouteCollection();
        $collection->add(...$routes);

        $groupAction = new RouteGroup($collection);
        $groupAction->setName('api.');

        $this->assertSame('api.foo', $routes[0]->getName());
        $this->assertSame('api.bar', $routes[1]->getName());
        $this->assertSame('api.baz', $routes[2]->getName());
    }

    public function testsetDefaults(): void
    {
        $routes = [
            new Route(\uniqid(), [RouteCollectorInterface::METHOD_GET], '/foo', 'phpinfo'),
            new Route(\uniqid(), [RouteCollectorInterface::METHOD_GET], '/bar', 'phpinfo'),
            new Route(\uniqid(), [RouteCollectorInterface::METHOD_GET], '/baz', 'phpinfo'),
        ];

        $newDefaults = [
            'foo' => 'test',
            'bar' => 523,
            'baz' => new Fixtures\TestRoute(),
        ];

        $additionalDefaults = [
            'qux'   => 'add',
            'quux'  => [Fixtures\BlankRequestHandler::class],
            'quuux' => 2564,
        ];

        $routes[0]->setDefaults($newDefaults);
        $routes[1]->setDefaults($newDefaults);
        $routes[2]->setDefaults($newDefaults);

        $collection = new RouteCollection();
        $collection->add(...$routes);

        $groupAction = new RouteGroup($collection);
        $groupAction->setDefaults($additionalDefaults);

        $expectedDefaults = \array_merge($newDefaults, $additionalDefaults);

        $this->assertSame($expectedDefaults, $routes[0]->getDefaults());
        $this->assertSame($expectedDefaults, $routes[1]->getDefaults());
        $this->assertSame($expectedDefaults, $routes[2]->getDefaults());
    }

    public function testAddPrefix(): void
    {
        $routes = [
            new Route(\uniqid(), [RouteCollectorInterface::METHOD_GET], '/foo', 'phpinfo'),
            new Route(\uniqid(), [RouteCollectorInterface::METHOD_GET], '/bar', 'phpinfo'),
        ];

        $collection = new RouteCollection();
        $collection->add(...$routes);

        $groupAction = new RouteGroup($collection);
        $groupAction->addPrefix('/api');

        $this->assertSame('/api/foo', $routes[0]->getPath());
        $this->assertSame('/api/bar', $routes[1]->getPath());
    }

    public function testAddDomain(): void
    {
        $routes = [
            new Route(\uniqid(), [RouteCollectorInterface::METHOD_GET], '/foo', 'phpinfo'),
            new Route(\uniqid(), [RouteCollectorInterface::METHOD_GET], '/bar', 'phpinfo'),
        ];

        $collection = new RouteCollection();
        $collection->add(...$routes);

        $routes[0]->setDomain('tests.com');

        $groupAction = new RouteGroup($collection);
        $groupAction->addDomain('biurad.com');

        $this->assertSame('biurad.com', $routes[0]->getDomain());
        $this->assertSame('biurad.com', $routes[1]->getDomain());
    }

    public function testAddScheme(): void
    {
        $routes = [
            new Route(\uniqid(), [RouteCollectorInterface::METHOD_GET], '/foo', 'phpinfo'),
            new Route(\uniqid(), [RouteCollectorInterface::METHOD_GET], '/bar', 'phpinfo'),
        ];

        $collection = new RouteCollection();
        $collection->add(...$routes);

        $routes[0]->setScheme('ftp');
        $routes[1]->setScheme('ftp');

        $groupAction = new RouteGroup($collection);
        $groupAction->addScheme('wss');

        $this->assertSame(['ftp', 'wss'], $routes[0]->getSchemes());
        $this->assertSame(['ftp', 'wss'], $routes[1]->getSchemes());
    }

    public function testAddMethod(): void
    {
        $routes = [
            new Route(\uniqid(), ['FOO'], '/foo', 'phpinfo'),
            new Route(\uniqid(), ['BAR'], '/bar', 'phpinfo'),
        ];

        $collection = new RouteCollection();
        $collection->add(...$routes);

        $groupAction = new RouteGroup($collection);
        $groupAction->addMethod('QUX', 'QUUX');

        $this->assertSame(['FOO', 'QUX', 'QUUX'], $routes[0]->getMethods());
        $this->assertSame(['BAR', 'QUX', 'QUUX'], $routes[1]->getMethods());
    }

    public function testAddMiddleware(): void
    {
        $routes = [
            new Route(\uniqid(), [RouteCollectorInterface::METHOD_GET], '/foo', 'phpinfo'),
            new Route(\uniqid(), [RouteCollectorInterface::METHOD_GET], '/bar', 'phpinfo'),
            new Route(\uniqid(), [RouteCollectorInterface::METHOD_GET], '/baz', 'phpinfo'),
        ];

        $newMiddlewares = [
            new Fixtures\NamedBlankMiddleware('foo'),
            new Fixtures\NamedBlankMiddleware('bar'),
            new Fixtures\NamedBlankMiddleware('baz'),
        ];

        $additionalMiddlewares = [
            new Fixtures\NamedBlankMiddleware('qux'),
            new Fixtures\NamedBlankMiddleware('quux'),
            new Fixtures\NamedBlankMiddleware('quuux'),
        ];

        $routes[0]->addMiddleware(...$newMiddlewares);
        $routes[1]->addMiddleware(...$newMiddlewares);
        $routes[2]->addMiddleware(...$newMiddlewares);

        $collection = new RouteCollection();
        $collection->add(...$routes);

        $groupAction = new RouteGroup($collection);
        $groupAction->addMiddleware(...$additionalMiddlewares);

        $expectedMiddlewares = \array_merge($newMiddlewares, $additionalMiddlewares);

        $this->assertSame($expectedMiddlewares, $routes[0]->getMiddlewares());
        $this->assertSame($expectedMiddlewares, $routes[1]->getMiddlewares());
        $this->assertSame($expectedMiddlewares, $routes[2]->getMiddlewares());
    }
}
