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

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Annotations\SimpleAnnotationReader;
use Flight\Routing\Annotation\Route as AnnotationRoute;
use Flight\Routing\Exceptions\InvalidAnnotationException;
use Flight\Routing\RouteCollector;
use Flight\Routing\RouteLoader;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use Spiral\Annotations\AnnotationLocator;
use Spiral\Tokenizer\ClassLocator;
use Symfony\Component\Finder\Finder;

/**
 * RouteLoaderTest
 */
class RouteLoaderTest extends TestCase
{
    protected function setUp(): void
    {
        AnnotationRegistry::registerLoader('class_exists');
    }

    /**
     * @runInSeparateProcess
     */
    public function testAttach(): void
    {
        $loader = new RouteLoader(new RouteCollector());
        $loader->attachArray([
            __DIR__ . '/Fixtures/Annotation/Route/Valid',
            'non-existing-file.php',
        ]);

        $this->assertSame([
            'do.action',
            'do.action_two',
            'flight_routing_tests_fixtures_annotation_route_valid_defaultnamecontroller_default',
            'home',
            'lol',
            'sub-dir:foo',
            'sub-dir:bar',
            'ping',
            'action',
            'hello_without_default',
            'hello_with_default',
        ], Fixtures\Helper::routesToNames($loader->load()));
    }

    /**
     * @runInSeparateProcess
     */
    public function testAttachArray(): void
    {
        $loader = new RouteLoader(new RouteCollector());
        $loader->attachArray([
            __DIR__ . '/Fixtures/Annotation/Route/Valid',
            __DIR__ . '/Fixtures/Annotation/Route/Containerable',
            __DIR__ . '/Fixtures/routes/foobar.php',
        ]);

        $this->assertCount(13, $loader->load());
    }

    /**
     * @dataProvider annotationTypeData
     * @runInSeparateProcess
     *
     * @param callalble|Reader $annotation
     */
    public function testLoad($annotation): void
    {
        if (\is_callable($annotation)) {
            $annotation = ($annotation)(__DIR__ . '/Fixtures/Annotation/Route/Valid');
        }

        $loader = new RouteLoader(new RouteCollector(), $annotation);
        $loader->attach(__DIR__ . '/Fixtures/Annotation/Route/Valid');
        $routes = $loader->load();

        $this->assertContains([
            'name'        => 'flight_routing_tests_fixtures_annotation_route_valid_defaultnamecontroller_default',
            'path'        => '/default',
            'domain'      => '',
            'methods'     => [RouteCollector::METHOD_GET, RouteCollector::METHOD_POST],
            'handler'     => [Fixtures\Annotation\Route\Valid\DefaultNameController::class, 'default'],
            'middlewares' => [],
            'schemes'     => [],
            'defaults'    => [],
            'patterns'    => [],
            'arguments'   => [],
        ], Fixtures\Helper::routesToArray($routes));

        $this->assertContains([
            'name'        => 'action',
            'path'        => '/{default}/path',
            'domain'      => '',
            'methods'     => [RouteCollector::METHOD_GET, RouteCollector::METHOD_POST],
            'handler'     => [Fixtures\Annotation\Route\Valid\DefaultValueController::class, 'action'],
            'middlewares' => [],
            'schemes'     => [],
            'defaults'    => [],
            'patterns'    => [],
            'arguments'   => [],
        ], Fixtures\Helper::routesToArray($routes));

        $this->assertContains([
            'name'        => 'hello_without_default',
            'path'        => '/hello/{name:\w+}',
            'domain'      => '',
            'methods'     => [RouteCollector::METHOD_GET, RouteCollector::METHOD_POST],
            'handler'     => [Fixtures\Annotation\Route\Valid\DefaultValueController::class, 'hello'],
            'middlewares' => [],
            'schemes'     => [],
            'defaults'    => [],
            'patterns'    => [],
            'arguments'   => [],
        ], Fixtures\Helper::routesToArray($routes));

        if (!$annotation instanceof AnnotationLocator) {
            $this->assertContains([
                'name'        => 'hello_with_default',
                'path'        => '/cool/{name=<Symfony>}',
                'domain'      => '',
                'methods'     => [RouteCollector::METHOD_GET, RouteCollector::METHOD_POST],
                'handler'     => [Fixtures\Annotation\Route\Valid\DefaultValueController::class, 'hello'],
                'middlewares' => [],
                'schemes'     => [],
                'defaults'    => [],
                'patterns'    => ['name' => '\w+'],
                'arguments'   => [],
            ], Fixtures\Helper::routesToArray($routes));
        }

        $this->assertContains([
            'name'        => 'home',
            'path'        => '/',
            'domain'      => 'biurad.com',
            'methods'     => [RouteCollector::METHOD_HEAD, RouteCollector::METHOD_GET],
            'handler'     => Fixtures\Annotation\Route\Valid\HomeRequestHandler::class,
            'middlewares' => [
                Fixtures\BlankMiddleware::class,
                Fixtures\BlankMiddleware::class,
            ],
            'schemes'     => ['https'],
            'defaults'    => [
                'foo' => 'bar',
                'bar' => 'baz',
            ],
            'patterns'    => [],
            'arguments'   => [],
        ], Fixtures\Helper::routesToArray($routes));

        $this->assertContains([
            'name'        => 'lol',
            'path'        => '/here',
            'domain'      => '',
            'methods'     => [RouteCollector::METHOD_GET, RouteCollector::METHOD_POST],
            'handler'     => Fixtures\Annotation\Route\Valid\InvokableController::class,
            'middlewares' => [],
            'schemes'     => ['https'],
            'defaults'    => [],
            'patterns'    => [],
            'arguments'   => [],
        ], Fixtures\Helper::routesToArray($routes));

        $this->assertContains([
            'name'        => 'ping',
            'path'        => '/ping',
            'domain'      => '',
            'methods'     => [RouteCollector::METHOD_HEAD, RouteCollector::METHOD_GET],
            'handler'     => Fixtures\Annotation\Route\Valid\PingRequestHandler::class,
            'middlewares' => [
                Fixtures\BlankMiddleware::class,
                Fixtures\BlankMiddleware::class,
            ],
            'schemes'     => [],
            'defaults'    => [
                'foo' => 'bar',
                'bar' => 'baz',
            ],
            'patterns'    => [],
            'arguments'   => [],
        ], Fixtures\Helper::routesToArray($routes));

        $this->assertContains([
            'name'        => 'do.action',
            'path'        => '/prefix/path',
            'domain'      => 'biurad.com',
            'methods'     => [RouteCollector::METHOD_GET, RouteCollector::METHOD_POST],
            'handler'     => [Fixtures\Annotation\Route\Valid\RouteWithPrefixController::class, 'action'],
            'middlewares' => [],
            'schemes'     => [],
            'defaults'    => [],
            'patterns'    => [],
            'arguments'   => [],
        ], Fixtures\Helper::routesToArray($routes));

        $this->assertContains([
            'name'        => 'do.action_two',
            'path'        => '/prefix/path_two',
            'domain'      => 'biurad.com',
            'methods'     => [RouteCollector::METHOD_GET, RouteCollector::METHOD_POST],
            'handler'     => [Fixtures\Annotation\Route\Valid\RouteWithPrefixController::class, 'actionTwo'],
            'middlewares' => [],
            'schemes'     => [],
            'defaults'    => [],
            'patterns'    => [],
            'arguments'   => [],
        ], Fixtures\Helper::routesToArray($routes));

        $this->assertContains([
            'name'        => 'sub-dir:foo',
            'path'        => '/sub-dir/foo',
            'domain'      => '',
            'methods'     => [RouteCollector::METHOD_HEAD, RouteCollector::METHOD_GET],
            'handler'     => Fixtures\Annotation\Route\Valid\Subdir\FooRequestHandler::class,
            'middlewares' => [
                Fixtures\BlankMiddleware::class,
                Fixtures\BlankMiddleware::class,
            ],
            'schemes'     => [],
            'defaults'    => [
                'foo' => 'bar',
                'bar' => 'baz',
            ],
            'patterns'    => [],
            'arguments'   => [],
        ], Fixtures\Helper::routesToArray($routes));

        $this->assertContains([
            'name'        => 'sub-dir:bar',
            'path'        => '/sub-dir/bar',
            'domain'      => '',
            'methods'     => [RouteCollector::METHOD_HEAD, RouteCollector::METHOD_GET],
            'handler'     => Fixtures\Annotation\Route\Valid\Subdir\BarRequestHandler::class,
            'middlewares' => [
                Fixtures\BlankMiddleware::class,
                Fixtures\BlankMiddleware::class,
            ],
            'schemes'     => [],
            'defaults'    => [
                'foo' => 'bar',
                'bar' => 'baz',
            ],
            'patterns'    => [],
            'arguments'   => [],
        ], Fixtures\Helper::routesToArray($routes));
    }

    /**
     * @runInSeparateProcess
     */
    public function testLoadWithCache(): void
    {
        $cache = $this->createMock(CacheInterface::class);

        $cache->method('has')
            ->will($this->returnCallback(function () {
                static $counter = 0;

                return ++$counter > 1;
            }));

        $cache->expects($this->exactly(1))
            ->method('set')
            ->willReturn(null);

        $cache->method('get')
            ->willReturn([
                Fixtures\BlankRequestHandler::class => [
                    'global' => new AnnotationRoute(['name' => 'foo', 'path' => '/foo', 'methods' => ['GET']]),
                ],
            ]);

        $loader = new RouteLoader(new RouteCollector());
        $loader->attach(__DIR__ . '/Fixtures/Annotation/Route/Empty');
        $loader->setCache($cache);

        // attempt to reload annotations...
        $loader->load();
        $loader->load();

        $this->assertCount(3, $loader->load());
        $this->assertContains([
            'name'        => 'foo',
            'path'        => '/foo',
            'domain'      => '',
            'methods'     => [RouteCollector::METHOD_GET],
            'handler'     => Fixtures\BlankRequestHandler::class,
            'middlewares' => [],
            'schemes'     => [],
            'defaults'    => [],
            'patterns'    => [],
            'arguments'   => [],
        ], Fixtures\Helper::routesToArray($loader->load()));
    }

    /**
     * @runInSeparateProcess
     */
    public function testLoadWithAbstractClass(): void
    {
        $this->expectException(InvalidAnnotationException::class);
        $this->expectExceptionMessage(
            'Annotations from class "Flight\Routing\Tests\Fixtures\Annotation\Route\Abstracts\AbstractController"' .
            ' cannot be read as it is abstract.'
        );

        $loader = new RouteLoader(new RouteCollector());
        $loader->attach(__DIR__ . '/Fixtures/Annotation/Route/Abstracts');

        $loader->load();
    }

    /**
     * @runInSeparateProcess
     *
     * @param string $resource
     * @param string $expectedException
     */
    public function testLoadInvalidAnnotatedClasses(): void
    {
        $loader = new RouteLoader(new RouteCollector());
        $loader->attach(__DIR__ . '/Fixtures/Annotation/Route/Invalid');

        // the given exception message should be tested through annotation class...
        $this->expectException(InvalidAnnotationException::class);

        $loader->load();
    }

    /**
     * @return string[]
     */
    public function annotationTypeData(): array
    {
        return [
            [new SimpleAnnotationReader()],
            [new AnnotationReader()],
            [[$this, 'annotationLocator']],
        ];
    }

    /**
     * @param string $directory
     *
     * @return AnnotationLocator
     */
    private function annotationLocator(string $directory): AnnotationLocator
    {
        $finder = (new Finder())->files()
            ->in($directory)
            ->name('*.php');
        $classes = new ClassLocator($finder);

        return new AnnotationLocator($classes);
    }
}
