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

namespace Flight\Routing\Tests\Annotation;

use Biurad\Annotations\AnnotationLoader;
use Biurad\Annotations\InvalidAnnotationException;
use Flight\Routing\Annotation;
use Flight\Routing\Exceptions\UriHandlerException;
use Flight\Routing\Handlers\ResourceHandler;
use Flight\Routing\Route;
use Flight\Routing\RouteCollection;
use Flight\Routing\Router;
use Spiral\Attributes\AnnotationReader;
use Spiral\Attributes\AttributeReader;
use Spiral\Attributes\Composite\MergeReader;
use Flight\Routing\Tests\Fixtures;
use PHPUnit\Framework\TestCase;

/**
 * ListenerTest.
 */
class ListenerTest extends TestCase
{
    /**
     * @runInSeparateProcess
     */
    public function testDefaultLoadWithListener(): void
    {
        $loader = $this->createLoader();
        $loader->listener(new Annotation\Listener(), 'test');
        $loader->resource(__DIR__ . '/../Fixtures/Annotation/Route/Valid');

        $collection = $loader->load('test');
        $this->assertInstanceOf(RouteCollection::class, $collection);

        $names = Fixtures\Helper::routesToNames($collection->getRoutes());
        \sort($names);

        $this->assertSame([
            'GET_HEAD_get',
            'GET_HEAD_get_1',
            'GET_HEAD_testing_',
            'GET_POST_default',
            'POST_post',
            'PUT_put',
            'action',
            'class_group@GET_HEAD_CONNECT_get',
            'class_group@POST_CONNECT_post',
            'class_group@PUT_CONNECT_put',
            'do.action',
            'do.action_two',
            'english_locale',
            'french_locale',
            'hello_with_default',
            'hello_without_default',
            'home',
            'lol',
            'method_not_array',
            'ping',
            'sub-dir:bar',
            'sub-dir:foo',
            'user__restful',
        ], $names);
        $this->assertCount(23, $names);
    }

    /**
     * @runInSeparateProcess
     */
    public function testDefaultLoadWithoutListener(): void
    {
        $loader = $this->createLoader();
        $loader->resource(__DIR__ . '/../Fixtures/Annotation/Route/Valid');
        $this->assertIsArray($collection = $loader->load(Annotation\Route::class));

        $collection = (new Annotation\Listener())->load($collection);
        $this->assertInstanceOf(RouteCollection::class, $collection);

        $names = Fixtures\Helper::routesToNames($collection->getRoutes());
        \sort($names);

        $this->assertSame([
            'GET_HEAD_get',
            'GET_HEAD_get_1',
            'GET_HEAD_testing_',
            'GET_POST_default',
            'POST_post',
            'PUT_put',
            'action',
            'class_group@GET_HEAD_CONNECT_get',
            'class_group@POST_CONNECT_post',
            'class_group@PUT_CONNECT_put',
            'do.action',
            'do.action_two',
            'english_locale',
            'french_locale',
            'hello_with_default',
            'hello_without_default',
            'home',
            'lol',
            'method_not_array',
            'ping',
            'sub-dir:bar',
            'sub-dir:foo',
            'user__restful',
        ], $names);
        $this->assertCount(23, $names);
    }

    /**
     * @runInSeparateProcess
     */
    public function testDefaultLoadWithNullPrefix(): void
    {
        $loader = $this->createLoader();
        $loader->listener(new Annotation\Listener(null, null), 'test');
        $loader->resource(__DIR__ . '/../Fixtures/Annotation/Route/Valid');

        $collection = $loader->load('test');
        $this->assertInstanceOf(RouteCollection::class, $collection);

        $names = Fixtures\Helper::routesToNames($collection->getRoutes());
        \sort($names);

        $this->assertEquals([
            'action',
            'class_group@GET_HEAD_CONNECT_get',
            'class_group@POST_CONNECT_post',
            'class_group@PUT_CONNECT_put',
            'do.action',
            'do.action_two',
            'english_locale',
            'french_locale',
            'hello_with_default',
            'hello_without_default',
            'home',
            'lol',
            'method_not_array',
            'ping',
            'sub-dir:bar',
            'sub-dir:foo',
            'user__restful',
        ], \array_values(\array_filter($names)));
        $this->assertCount(23, $names);
    }

    /**
     * @runInSeparateProcess
     */
    public function testResourceCount(): void
    {
        $loader = $this->createLoader();
        $loader->listener(new Annotation\Listener());
        $loader->resource(...[
            __DIR__ . '/../Fixtures/Annotation/Route/Valid',
            __DIR__ . '/../Fixtures/Annotation/Route/Containerable',
            __DIR__ . '/../Fixtures/Annotation/Route/Attribute',
            __DIR__ . '/../Fixtures/Annotation/Route/Abstracts', // Abstract should be excluded
        ]);

        $collection = new RouteCollection();
        $collection->populate($loader->load(Annotation\Listener::class));

        $this->assertCount(26, $collection->getRoutes());
    }

    /**
     * @requires OS WIN32|WINNT|Linux
     * @runInSeparateProcess
     */
    public function testLoadingWithAnnotationBuildWithPrefix(): void
    {
        $loader = $this->createLoader();
        $loader->listener(new Annotation\Listener($collection = new RouteCollection(), 'annotated'));
        $loader->resource(__DIR__ . '/../Fixtures/Annotation/Route/Valid');

        $loader->load();

        $routes = $collection->getRoutes();
        \uasort($routes, static function (Route $a, Route $b): int {
            return \strcmp($a->getName(), $b->getName());
        });

        $routes = Fixtures\Helper::routesToArray($routes);

        $this->assertEquals([
            'name' => 'GET_POST_annotated_default',
            'path' => '/default',
            'hosts' => [],
            'methods' => [Router::METHOD_GET, Router::METHOD_POST],
            'handler' => [Fixtures\Annotation\Route\Valid\DefaultNameController::class, 'default'],
            'schemes' => [],
            'defaults' => [],
            'patterns' => [],
            'arguments' => [],
        ], $routes[3]);

        $this->assertEquals([
            'name' => 'GET_HEAD_annotated_get',
            'path' => '/get',
            'hosts' => [],
            'methods' => [Router::METHOD_GET, Router::METHOD_HEAD],
            'handler' => [Fixtures\Annotation\Route\Valid\MultipleMethodRouteController::class, 'default'],
            'schemes' => [],
            'defaults' => [],
            'patterns' => [],
            'arguments' => [],
        ], $routes[0]);

        $this->assertEquals([
            'name' => 'GET_HEAD_annotated_get_1',
            'path' => '/get',
            'hosts' => [],
            'methods' => [Router::METHOD_GET, Router::METHOD_HEAD],
            'handler' => [Fixtures\Annotation\Route\Valid\DefaultNameController::class, 'default'],
            'schemes' => [],
            'defaults' => [],
            'patterns' => [],
            'arguments' => [],
        ], $routes[1]);

        $this->assertEquals([
            'name' => 'POST_annotated_post',
            'path' => '/post',
            'hosts' => [],
            'methods' => [Router::METHOD_POST],
            'handler' => [Fixtures\Annotation\Route\Valid\MultipleMethodRouteController::class, 'default'],
            'schemes' => [],
            'defaults' => [],
            'patterns' => [],
            'arguments' => [],
        ], $routes[4]);

        $this->assertEquals([
            'name' => 'PUT_annotated_put',
            'path' => '/put',
            'hosts' => [],
            'methods' => [Router::METHOD_PUT],
            'handler' => [Fixtures\Annotation\Route\Valid\MultipleMethodRouteController::class, 'default'],
            'schemes' => [],
            'defaults' => [],
            'patterns' => [],
            'arguments' => [],
        ], $routes[5]);

        $this->assertEquals([
            'name' => 'class_group@GET_HEAD_CONNECT_annotated_get',
            'path' => '/get',
            'hosts' => [],
            'methods' => [Router::METHOD_GET, Router::METHOD_HEAD, Router::METHOD_CONNECT],
            'handler' => [Fixtures\Annotation\Route\Valid\ClassGroupWithoutPath::class, 'default'],
            'schemes' => [],
            'defaults' => [],
            'patterns' => [],
            'arguments' => [],
        ], $routes[7]);

        $this->assertEquals([
            'name' => 'class_group@POST_CONNECT_annotated_post',
            'path' => '/post',
            'hosts' => [],
            'methods' => [Router::METHOD_POST, Router::METHOD_CONNECT],
            'handler' => [Fixtures\Annotation\Route\Valid\ClassGroupWithoutPath::class, 'default'],
            'schemes' => [],
            'defaults' => [],
            'patterns' => [],
            'arguments' => [],
        ], $routes[8]);

        $this->assertEquals([
            'name' => 'class_group@PUT_CONNECT_annotated_put',
            'path' => '/put',
            'hosts' => [],
            'methods' => [Router::METHOD_PUT, Router::METHOD_CONNECT],
            'handler' => [Fixtures\Annotation\Route\Valid\ClassGroupWithoutPath::class, 'default'],
            'schemes' => [],
            'defaults' => [],
            'patterns' => [],
            'arguments' => [],
        ], $routes[9]);

        $this->assertEquals([
            'name' => 'GET_HEAD_annotated_testing_',
            'path' => '/testing/',
            'hosts' => [],
            'methods' => [Router::METHOD_GET, Router::METHOD_HEAD],
            'handler' => [Fixtures\Annotation\Route\Valid\MethodOnRoutePattern::class, 'handleSomething'],
            'schemes' => [],
            'defaults' => [],
            'patterns' => [],
            'arguments' => [],
        ], $routes[2]);

        $this->assertEquals([
            'name' => 'english_locale',
            'path' => '/en/locale',
            'hosts' => [],
            'methods' => [Router::METHOD_GET, Router::METHOD_HEAD],
            'handler' => [Fixtures\Annotation\Route\Valid\MultipleClassRouteController::class, 'default'],
            'schemes' => [],
            'defaults' => [],
            'patterns' => [],
            'arguments' => [],
        ], $routes[12]);

        $this->assertEquals([
            'name' => 'french_locale',
            'path' => '/fr/locale',
            'hosts' => [],
            'methods' => [Router::METHOD_GET, Router::METHOD_HEAD],
            'handler' => [Fixtures\Annotation\Route\Valid\MultipleClassRouteController::class, 'default'],
            'schemes' => [],
            'defaults' => [],
            'patterns' => [],
            'arguments' => [],
        ], $routes[13]);

        $this->assertEquals([
            'name' => 'action',
            'path' => '/{default}/path',
            'hosts' => [],
            'methods' => [Router::METHOD_GET, Router::METHOD_POST],
            'handler' => [Fixtures\Annotation\Route\Valid\DefaultValueController::class, 'action'],
            'schemes' => [],
            'defaults' => [],
            'patterns' => [],
            'arguments' => [],
        ], $routes[6]);

        $this->assertEquals([
            'name' => 'hello_without_default',
            'path' => '/hello/{name:\w+}',
            'hosts' => [],
            'methods' => [Router::METHOD_GET, Router::METHOD_POST],
            'handler' => [Fixtures\Annotation\Route\Valid\DefaultValueController::class, 'hello'],
            'schemes' => [],
            'defaults' => [],
            'patterns' => [],
            'arguments' => [],
        ], $routes[15]);

        $this->assertEquals([
            'name' => 'hello_with_default',
            'path' => '/cool/{name=<Symfony>}',
            'hosts' => [],
            'methods' => [Router::METHOD_GET, Router::METHOD_POST],
            'handler' => [Fixtures\Annotation\Route\Valid\DefaultValueController::class, 'hello'],
            'schemes' => [],
            'defaults' => [],
            'patterns' => ['name' => '\w+'],
            'arguments' => [],
        ], $routes[14]);

        $this->assertEquals([
            'name' => 'home',
            'path' => '/',
            'hosts' => ['biurad.com'],
            'methods' => [Router::METHOD_HEAD, Router::METHOD_GET],
            'handler' => Fixtures\Annotation\Route\Valid\HomeRequestHandler::class,
            'schemes' => ['https'],
            'defaults' => [
                'foo' => 'bar',
                'bar' => 'baz',
            ],
            'patterns' => [],
            'arguments' => [],
        ], $routes[16]);

        $this->assertEquals([
            'name' => 'lol',
            'path' => '/here',
            'hosts' => [],
            'methods' => [Router::METHOD_GET, Router::METHOD_POST],
            'handler' => Fixtures\Annotation\Route\Valid\InvokableController::class,
            'schemes' => ['https'],
            'defaults' => [],
            'patterns' => [],
            'arguments' => ['hello' => 'world'],
        ], $routes[17]);

        $this->assertEquals([
            'name' => 'ping',
            'path' => '/ping',
            'hosts' => [],
            'methods' => [Router::METHOD_HEAD, Router::METHOD_GET],
            'handler' => Fixtures\Annotation\Route\Valid\PingRequestHandler::class,
            'schemes' => [],
            'defaults' => [
                'foo' => 'bar',
                'bar' => 'baz',
            ],
            'patterns' => [],
            'arguments' => [],
        ], $routes[19]);

        $this->assertEquals([
            'name' => 'method_not_array',
            'path' => '/method_not_array',
            'hosts' => [],
            'methods' => [Router::METHOD_GET],
            'handler' => Fixtures\Annotation\Route\Valid\MethodsNotArray::class,
            'schemes' => [],
            'defaults' => [],
            'patterns' => [],
            'arguments' => [],
        ], $routes[18]);

        $this->assertEquals([
            'name' => 'do.action',
            'path' => '/prefix/path',
            'hosts' => ['biurad.com'],
            'methods' => [Router::METHOD_GET, Router::METHOD_POST],
            'handler' => [Fixtures\Annotation\Route\Valid\RouteWithPrefixController::class, 'action'],
            'schemes' => [],
            'defaults' => [],
            'patterns' => [],
            'arguments' => [],
        ], $routes[10]);

        $this->assertEquals([
            'name' => 'do.action_two',
            'path' => '/prefix/path_two',
            'hosts' => ['biurad.com'],
            'methods' => [Router::METHOD_GET, Router::METHOD_POST],
            'handler' => [Fixtures\Annotation\Route\Valid\RouteWithPrefixController::class, 'actionTwo'],
            'schemes' => [],
            'defaults' => [],
            'patterns' => [],
            'arguments' => [],
        ], $routes[11]);

        $this->assertEquals([
            'name' => 'sub-dir:foo',
            'path' => '/sub-dir/foo',
            'hosts' => [],
            'methods' => [Router::METHOD_HEAD, Router::METHOD_GET],
            'handler' => Fixtures\Annotation\Route\Valid\Subdir\FooRequestHandler::class,
            'schemes' => [],
            'defaults' => [
                'foo' => 'bar',
                'bar' => 'baz',
            ],
            'patterns' => [],
            'arguments' => [],
        ], $routes[21]);

        $this->assertEquals([
            'name' => 'sub-dir:bar',
            'path' => '/sub-dir/bar',
            'hosts' => [],
            'methods' => [Router::METHOD_HEAD, Router::METHOD_GET],
            'handler' => Fixtures\Annotation\Route\Valid\Subdir\BarRequestHandler::class,
            'schemes' => [],
            'defaults' => [
                'foo' => 'bar',
                'bar' => 'baz',
            ],
            'patterns' => [],
            'arguments' => [],
        ], $routes[20]);

        $this->assertEquals([
            'name' => 'user__restful',
            'path' => '/user/{id:\d+}',
            'hosts' => [],
            'methods' => [],
            'handler' => ResourceHandler::class,
            'schemes' => [],
            'defaults' => [],
            'patterns' => [],
            'arguments' => [],
        ], $routes[22]);
    }

    /**
     * @runInSeparateProcess
     */
    public function testLoadAttribute(): void
    {
        $loader = new AnnotationLoader(new AttributeReader());
        $loader->listener(new Annotation\Listener());
        $loader->resource(__DIR__ . '/../Fixtures/Annotation/Route/Attribute');

        $router = Router::withCollection($loader->load(Annotation\Listener::class));
        $routes = $router->getMatcher()->getRoutes();

        $this->assertEquals([
            [
                'name' => 'attribute_specific_name',
                'path' => '/defaults/{locale}/specific-name',
                'hosts' => [],
                'methods' => [Router::METHOD_GET],
                'handler' => [Fixtures\Annotation\Route\Attribute\GlobalDefaultsClass::class, 'withName'],
                'schemes' => [],
                'defaults' => ['foo' => 'bar'],
                'patterns' => ['locale' => 'en|fr'],
                'arguments' => [],
            ],
            [
                'name' => 'attribute_GET_HEAD_defaults_locale_specific_none',
                'path' => '/defaults/{locale}/specific-none',
                'hosts' => [],
                'methods' => [Router::METHOD_GET, Router::METHOD_HEAD],
                'handler' => [Fixtures\Annotation\Route\Attribute\GlobalDefaultsClass::class, 'noName'],
                'schemes' => [],
                'defaults' => ['foo' => 'bar'],
                'patterns' => ['locale' => 'en|fr'],
                'arguments' => [],
            ],
        ], Fixtures\Helper::routesToArray($routes));
    }

    public function testInvalidPath(): void
    {
        $loader = $this->createLoader();
        $loader->listener(new Annotation\Listener());
        $loader->resource(Fixtures\Annotation\Route\Invalid\PathEmpty::class);

        $this->expectExceptionObject(new UriHandlerException('The route pattern "//localhost" is invalid as route path must be present in pattern.'));
        $loader->load();
    }

    public function testClassGroupWithResource(): void
    {
        $loader = $this->createLoader();
        $loader->listener(new Annotation\Listener());
        $loader->resource(Fixtures\Annotation\Route\Invalid\ClassGroupWithResource::class);

        $this->expectExceptionObject(new InvalidAnnotationException('Restful annotated class cannot contain annotated method(s).'));
        $loader->load();
    }

    public function testMethodWithResource(): void
    {
        $loader = $this->createLoader();
        $loader->listener(new Annotation\Listener());
        $loader->resource(Fixtures\Annotation\Route\Invalid\MethodWithResource::class);

        $this->expectExceptionObject(new InvalidAnnotationException('Restful annotation is only supported on classes.'));
        $loader->load();
    }

    protected function createLoader(): AnnotationLoader
    {
        return new AnnotationLoader(new MergeReader([new AnnotationReader(), new AttributeReader()]));
    }
}
