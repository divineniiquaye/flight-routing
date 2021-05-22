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

namespace Flight\Routing\Tests\Annotation;

use Biurad\Annotations\AnnotationLoader;
use Biurad\Annotations\InvalidAnnotationException;
use Flight\Routing\Annotation\Listener;
use Flight\Routing\Handlers\ResourceHandler;
use Flight\Routing\Router;
use Flight\Routing\Tests\BaseTestCase;
use Spiral\Attributes\AnnotationReader;
use Spiral\Attributes\AttributeReader;
use Spiral\Attributes\Composite\MergeReader;
use Flight\Routing\Tests\Fixtures;

/**
 * ListenerTest
 */
class ListenerTest extends BaseTestCase
{
    /** @var AnnotationLoader */
    protected $loader;

    protected function setUp(): void
    {
        $loader = new AnnotationLoader(new MergeReader([new AnnotationReader(), new AttributeReader()]));
        $loader->listener(new Listener());

        $this->loader = $loader;
    }

    /**
     * @runInSeparateProcess
     */
    public function testDoctrineResource(): void
    {
        $loader = clone $this->loader;
        $loader->resource(...[
            __DIR__ . '/../Fixtures/Annotation/Route/Valid',
            'non-existing-file.php',
        ]);

        $router = $this->getRouter();
        $router->loadAnnotation($loader);

        $routes = Fixtures\Helper::routesToNames($router->getIterator());
        \sort($routes);

        $this->assertSame([
            'GET_HEAD_annotated_get',
            'GET_HEAD_annotated_get_1',
            'GET_HEAD_annotated_testing_',
            'GET_POST_annotated_default',
            'POST_annotated_post',
            'PUT_annotated_put',
            'action',
            'class_group@CONNECT_GET_HEAD_annotated_get',
            'class_group@CONNECT_POST_annotated_post',
            'class_group@CONNECT_PUT_annotated_put',
            'do.action',
            'do.action_two',
            'english_locale',
            'french_locale',
            'hello_with_default',
            'hello_without_default',
            'home',
            'lol',
            'method_not_array',
            'middlewares_not_array',
            'ping',
            'sub-dir:bar',
            'sub-dir:foo',
            'user__restful',
        ], $routes);
    }

    /**
     * @runInSeparateProcess
     */
    public function testResourceCount(): void
    {
        $loader = clone $this->loader;
        $loader->resource(...[
            __DIR__ . '/../Fixtures/Annotation/Route/Valid',
            __DIR__ . '/../Fixtures/Annotation/Route/Containerable',
            __DIR__ . '/../Fixtures/Annotation/Route/Attribute',
            __DIR__ . '/../Fixtures/Annotation/Route/Abstracts', // Abstract should be excluded
        ]);

        $router = $this->getRouter();
        $router->loadAnnotation($loader);

        $this->assertCount(27, $router->getIterator());
    }

    /**
     * @runInSeparateProcess
     */
    public function testLoad(): void
    {
        $loader = clone $this->loader;
        $loader->resource(__DIR__ . '/../Fixtures/Annotation/Route/Valid');

        $router = $this->getRouter();
        $router->loadAnnotation($loader);

        $routes = Fixtures\Helper::routesToArray($router->getIterator());
        \usort($routes, function (array $a, array $b) {
            return \strcmp($a["name"], $b["name"]);
        });

        $this->assertEquals([
            'name'        => 'GET_POST_annotated_default',
            'path'        => '/default',
            'domain'      => [],
            'methods'     => [Router::METHOD_GET, Router::METHOD_POST],
            'handler'     => [Fixtures\Annotation\Route\Valid\DefaultNameController::class, 'default'],
            'middlewares' => [],
            'schemes'     => [],
            'defaults'    => [],
            'patterns'    => [],
            'arguments'   => [],
        ], $routes[3]);

        $this->assertEquals([
            'name'        => 'GET_HEAD_annotated_get',
            'path'        => '/get',
            'domain'      => [],
            'methods'     => [Router::METHOD_GET, Router::METHOD_HEAD],
            'handler'     => [Fixtures\Annotation\Route\Valid\MultipleMethodRouteController::class, 'default'],
            'middlewares' => [],
            'schemes'     => [],
            'defaults'    => [],
            'patterns'    => [],
            'arguments'   => [],
        ], $routes[0]);

        $this->assertEquals([
            'name'        => 'GET_HEAD_annotated_get_1',
            'path'        => '/get',
            'domain'      => [],
            'methods'     => [Router::METHOD_GET, Router::METHOD_HEAD],
            'handler'     => [Fixtures\Annotation\Route\Valid\DefaultNameController::class, 'default'],
            'middlewares' => [],
            'schemes'     => [],
            'defaults'    => [],
            'patterns'    => [],
            'arguments'   => [],
        ], $routes[1]);

        $this->assertEquals([
            'name'        => 'POST_annotated_post',
            'path'        => '/post',
            'domain'      => [],
            'methods'     => [Router::METHOD_POST],
            'handler'     => [Fixtures\Annotation\Route\Valid\MultipleMethodRouteController::class, 'default'],
            'middlewares' => [],
            'schemes'     => [],
            'defaults'    => [],
            'patterns'    => [],
            'arguments'   => [],
        ], $routes[4]);

        $this->assertEquals([
            'name'        => 'PUT_annotated_put',
            'path'        => '/put',
            'domain'      => [],
            'methods'     => [Router::METHOD_PUT],
            'handler'     => [Fixtures\Annotation\Route\Valid\MultipleMethodRouteController::class, 'default'],
            'middlewares' => [],
            'schemes'     => [],
            'defaults'    => [],
            'patterns'    => [],
            'arguments'   => [],
        ], $routes[5]);

        $this->assertEquals([
            'name'        => 'class_group@CONNECT_GET_HEAD_annotated_get',
            'path'        => '/get',
            'domain'      => [],
            'methods'     => [Router::METHOD_CONNECT, Router::METHOD_GET, Router::METHOD_HEAD],
            'handler'     => [Fixtures\Annotation\Route\Valid\MultipleMethodRouteController::class, 'default'],
            'middlewares' => [],
            'schemes'     => [],
            'defaults'    => [],
            'patterns'    => [],
            'arguments'   => [],
        ], $routes[7]);

        $this->assertEquals([
            'name'        => 'class_group@CONNECT_POST_annotated_post',
            'path'        => '/post',
            'domain'      => [],
            'methods'     => [Router::METHOD_CONNECT, Router::METHOD_POST],
            'handler'     => [Fixtures\Annotation\Route\Valid\MultipleMethodRouteController::class, 'default'],
            'middlewares' => [],
            'schemes'     => [],
            'defaults'    => [],
            'patterns'    => [],
            'arguments'   => [],
        ], $routes[8]);

        $this->assertEquals([
            'name'        => 'class_group@CONNECT_PUT_annotated_put',
            'path'        => '/put',
            'domain'      => [],
            'methods'     => [Router::METHOD_CONNECT, Router::METHOD_PUT],
            'handler'     => [Fixtures\Annotation\Route\Valid\MultipleMethodRouteController::class, 'default'],
            'middlewares' => [],
            'schemes'     => [],
            'defaults'    => [],
            'patterns'    => [],
            'arguments'   => [],
        ], $routes[9]);

        $this->assertEquals([
            'name'        => 'GET_HEAD_annotated_testing_',
            'path'        => 'testing/',
            'domain'      => [],
            'methods'     => [Router::METHOD_GET, Router::METHOD_HEAD],
            'handler'     => [Fixtures\Annotation\Route\Valid\MethodOnRoutePattern::class, 'handleSomething'],
            'middlewares' => [],
            'schemes'     => [],
            'defaults'    => [],
            'patterns'    => [],
            'arguments'   => [],
        ], $routes[2]);

        $this->assertEquals([
            'name'        => 'english_locale',
            'path'        => '/en/locale',
            'domain'      => [],
            'methods'     => [Router::METHOD_GET, Router::METHOD_HEAD],
            'handler'     => [Fixtures\Annotation\Route\Valid\MultipleClassRouteController::class, 'default'],
            'middlewares' => [],
            'schemes'     => [],
            'defaults'    => [],
            'patterns'    => [],
            'arguments'   => [],
        ], $routes[12]);

        $this->assertEquals([
            'name'        => 'french_locale',
            'path'        => '/fr/locale',
            'domain'      => [],
            'methods'     => [Router::METHOD_GET, Router::METHOD_HEAD],
            'handler'     => [Fixtures\Annotation\Route\Valid\MultipleClassRouteController::class, 'default'],
            'middlewares' => [],
            'schemes'     => [],
            'defaults'    => [],
            'patterns'    => [],
            'arguments'   => [],
        ], $routes[13]);

        $this->assertEquals([
            'name'        => 'action',
            'path'        => '/{default}/path',
            'domain'      => [],
            'methods'     => [Router::METHOD_GET, Router::METHOD_POST],
            'handler'     => [Fixtures\Annotation\Route\Valid\DefaultValueController::class, 'action'],
            'middlewares' => [],
            'schemes'     => [],
            'defaults'    => [],
            'patterns'    => [],
            'arguments'   => [],
        ], $routes[6]);

        $this->assertEquals([
            'name'        => 'hello_without_default',
            'path'        => '/hello/{name:\w+}',
            'domain'      => [],
            'methods'     => [Router::METHOD_GET, Router::METHOD_POST],
            'handler'     => [Fixtures\Annotation\Route\Valid\DefaultValueController::class, 'hello'],
            'middlewares' => [],
            'schemes'     => [],
            'defaults'    => [],
            'patterns'    => [],
            'arguments'   => [],
        ], $routes[15]);

        $this->assertEquals([
            'name'        => 'hello_with_default',
            'path'        => '/cool/{name=<Symfony>}',
            'domain'      => [],
            'methods'     => [Router::METHOD_GET, Router::METHOD_POST],
            'handler'     => [Fixtures\Annotation\Route\Valid\DefaultValueController::class, 'hello'],
            'middlewares' => [],
            'schemes'     => [],
            'defaults'    => [],
            'patterns'    => ['name' => '\w+'],
            'arguments'   => [],
        ], $routes[14]);

        $this->assertEquals([
            'name'        => 'home',
            'path'        => '/',
            'domain'      => ['biurad.com'],
            'methods'     => [Router::METHOD_HEAD, Router::METHOD_GET],
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
        ], $routes[16]);

        $this->assertEquals([
            'name'        => 'lol',
            'path'        => '/here',
            'domain'      => [],
            'methods'     => [Router::METHOD_GET, Router::METHOD_POST],
            'handler'     => Fixtures\Annotation\Route\Valid\InvokableController::class,
            'middlewares' => [],
            'schemes'     => ['https'],
            'defaults'    => [],
            'patterns'    => [],
            'arguments'   => [],
        ], $routes[17]);

        $this->assertEquals([
            'name'        => 'ping',
            'path'        => '/ping',
            'domain'      => [],
            'methods'     => [Router::METHOD_HEAD, Router::METHOD_GET],
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
        ], $routes[20]);

        $this->assertEquals([
            'name'        => 'method_not_array',
            'path'        => '/method_not_array',
            'domain'      => [],
            'methods'     => [Router::METHOD_GET],
            'handler'     => Fixtures\Annotation\Route\Valid\MethodsNotArray::class,
            'middlewares' => [],
            'schemes'     => [],
            'defaults'    => [],
            'patterns'    => [],
            'arguments'   => [],
        ], $routes[18]);

        $this->assertEquals([
            'name'        => 'middlewares_not_array',
            'path'        => '/middlewares_not_array',
            'domain'      => [],
            'methods'     => [Router::METHOD_GET],
            'handler'     => Fixtures\Annotation\Route\Valid\MiddlewaresNotArray::class,
            'middlewares' => [Fixtures\BlankMiddleware::class],
            'schemes'     => [],
            'defaults'    => [],
            'patterns'    => [],
            'arguments'   => [],
        ], $routes[19]);

        $this->assertEquals([
            'name'        => 'do.action',
            'path'        => '/prefix/path',
            'domain'      => ['biurad.com'],
            'methods'     => [Router::METHOD_GET, Router::METHOD_POST],
            'handler'     => [Fixtures\Annotation\Route\Valid\RouteWithPrefixController::class, 'action'],
            'middlewares' => [],
            'schemes'     => [],
            'defaults'    => [],
            'patterns'    => [],
            'arguments'   => [],
        ], $routes[10]);

        $this->assertEquals([
            'name'        => 'do.action_two',
            'path'        => '/prefix/path_two',
            'domain'      => ['biurad.com'],
            'methods'     => [Router::METHOD_GET, Router::METHOD_POST],
            'handler'     => [Fixtures\Annotation\Route\Valid\RouteWithPrefixController::class, 'actionTwo'],
            'middlewares' => [],
            'schemes'     => [],
            'defaults'    => [],
            'patterns'    => [],
            'arguments'   => [],
        ], $routes[11]);

        $this->assertEquals([
            'name'        => 'sub-dir:foo',
            'path'        => '/sub-dir/foo',
            'domain'      => [],
            'methods'     => [Router::METHOD_HEAD, Router::METHOD_GET],
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
        ], $routes[22]);

        $this->assertEquals([
            'name'        => 'sub-dir:bar',
            'path'        => '/sub-dir/bar',
            'domain'      => [],
            'methods'     => [Router::METHOD_HEAD, Router::METHOD_GET],
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
        ], $routes[21]);

        $this->assertEquals([
            'name'        => 'user__restful',
            'path'        => '/user/{id:\d+}',
            'domain'      => [],
            'methods'     => [],
            'handler'     => ResourceHandler::class,
            'middlewares' => [],
            'schemes'     => [],
            'defaults'    => [],
            'patterns'    => [],
            'arguments'   => [],
        ], $routes[23]);
    }

    /**
     * @runInSeparateProcess
     */
    public function testLoadAttribute(): void
    {
        $loader = new AnnotationLoader(new AttributeReader());

        $loader->listener(new Listener());
        $loader->resource(__DIR__ . '/../Fixtures/Annotation/Route/Attribute');

        $router = $this->getRouter();
        $router->loadAnnotation($loader);

        $routes = $router->getIterator();

        $this->assertContains([
            'name'        => 'attribute_specific_name',
            'path'        => '/defaults/{locale}/specific-name',
            'domain'      => [],
            'methods'     => [Router::METHOD_GET],
            'handler'     => [Fixtures\Annotation\Route\Attribute\GlobalDefaultsClass::class, 'withName'],
            'middlewares' => [],
            'schemes'     => [],
            'defaults'    => ['foo' => 'bar'],
            'patterns'    => ['locale' => 'en|fr'],
            'arguments'   => [],
        ], Fixtures\Helper::routesToArray($routes));

        $this->assertContains([
            'name'        => 'attribute_GET_HEAD_annotated_specific_none',
            'path'        => '/defaults/{locale}/specific-none',
            'domain'      => [],
            'methods'     => [Router::METHOD_GET, Router::METHOD_HEAD],
            'handler'     => [Fixtures\Annotation\Route\Attribute\GlobalDefaultsClass::class, 'noName'],
            'middlewares' => [],
            'schemes'     => [],
            'defaults'    => ['foo' => 'bar'],
            'patterns'    => ['locale' => 'en|fr'],
            'arguments'   => [],
        ], Fixtures\Helper::routesToArray($routes));
    }

    /**
     * @dataProvider invalidAnnotatedClasses
     * @runInSeparateProcess
     *
     * @param string $class
     * @param string $message
     */
    public function testLoadInvalidAnnotatedClasses(string $class, string $message): void
    {
        $loader = clone $this->loader;
        $loader->resource($class);
        $router = $this->getRouter();

        // the given exception message should be tested through annotation class...
        $this->expectException(InvalidAnnotationException::class);
        $this->expectExceptionMessage($message);

        $router->loadAnnotation($loader);
    }

    /**
     * @return string[]
     */
    public function invalidAnnotatedClasses(): array
    {
        return [
            [Fixtures\Annotation\Route\Invalid\DefaultsNotArray::class, '@Route.defaults must contain a sequence array of string keys and values. eg: [key => value]'],
            [Fixtures\Annotation\Route\Invalid\MethodsNotStringable::class, '@Route.methods must contain only an array list of strings.'],
            [Fixtures\Annotation\Route\Invalid\MiddlewaresNotStringable::class, '@Route.middlewares must contain only an array list of strings.'],
            [Fixtures\Annotation\Route\Invalid\NameNotString::class, '@Route.name must contain only a type of string.'],
            [Fixtures\Annotation\Route\Invalid\PathNotString::class, '@Route.path must contain only a type of string.'],
            [Fixtures\Annotation\Route\Invalid\PathEmpty::class, '@Route.path must not be left empty.'],
            [Fixtures\Annotation\Route\Invalid\PathMissing::class, '@Route.path must not be left empty.'],
        ];
    }
}
