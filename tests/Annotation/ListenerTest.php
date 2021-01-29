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
use Doctrine\Common\Annotations\AnnotationRegistry;
use Flight\Routing\Annotation\Listener;
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
        AnnotationRegistry::registerLoader('class_exists');

        $loader = new AnnotationLoader(new MergeReader([new AnnotationReader(), new AttributeReader()]));
        $loader->attachListener(new Listener());

        $this->loader = $loader;
    }

    /**
     * @runInSeparateProcess
     */
    public function testAttach(): void
    {
        $loader = clone $this->loader;
        $loader->attach(...[
            __DIR__ . '/../Fixtures/Annotation/Route/Valid',
            'non-existing-file.php',
        ]);

        $router = $this->getRouter();
        $router->loadAnnotation($loader);

        $routes = Fixtures\Helper::routesToNames($router->getCollection()->getRoutes());
        \sort($routes);

        $this->assertSame([
            'action',
            'class_group@flight_routing_tests_fixtures_annotation_route_valid_multiplemethodroutecontroller_default',
            'class_group@flight_routing_tests_fixtures_annotation_route_valid_multiplemethodroutecontroller_default_1',
            'class_group@flight_routing_tests_fixtures_annotation_route_valid_multiplemethodroutecontroller_default_2',
            'do.action',
            'do.action_two',
            'english_locale',
            'flight_routing_tests_fixtures_annotation_route_valid_defaultnamecontroller_default',
            'flight_routing_tests_fixtures_annotation_route_valid_methodonroutepattern',
            'flight_routing_tests_fixtures_annotation_route_valid_multiplemethodroutecontroller_default',
            'flight_routing_tests_fixtures_annotation_route_valid_multiplemethodroutecontroller_default_1',
            'flight_routing_tests_fixtures_annotation_route_valid_multiplemethodroutecontroller_default_2',
            'french_locale',
            'hello_with_default',
            'hello_without_default',
            'home',
            'lol',
            'ping',
            'sub-dir:bar',
            'sub-dir:foo',
            'user__restful',
        ], $routes);
    }

    /**
     * @runInSeparateProcess
     */
    public function testAttachArray(): void
    {
        $loader = clone $this->loader;
        $loader->attach(...[
            __DIR__ . '/../Fixtures/Annotation/Route/Valid',
            __DIR__ . '/../Fixtures/Annotation/Route/Containerable',
            __DIR__ . '/../Fixtures/Annotation/Route/Attribute',
        ]);

        $router = $this->getRouter();
        $router->loadAnnotation($loader);

        $this->assertCount(24, $router->getCollection()->getRoutes());
    }

    /**
     * @runInSeparateProcess
     */
    public function testLoad(): void
    {
        $loader = clone $this->loader;
        $loader->attach(__DIR__ . '/../Fixtures/Annotation/Route/Valid');

        $router = $this->getRouter();
        $router->loadAnnotation($loader);

        $routes = $router->getCollection()->getRoutes();

        $this->assertContains([
            'name'        => 'flight_routing_tests_fixtures_annotation_route_valid_defaultnamecontroller_default',
            'path'        => '/default',
            'domain'      => [],
            'methods'     => [Router::METHOD_GET, Router::METHOD_POST],
            'handler'     => [Fixtures\Annotation\Route\Valid\DefaultNameController::class, 'default'],
            'middlewares' => [],
            'schemes'     => [],
            'defaults'    => [],
            'patterns'    => [],
            'arguments'   => [],
        ], Fixtures\Helper::routesToArray($routes));

        $this->assertContains([
            'name'        => 'flight_routing_tests_fixtures_annotation_route_valid_multiplemethodroutecontroller_default',
            'path'        => '/get',
            'domain'      => [],
            'methods'     => [Router::METHOD_GET, Router::METHOD_HEAD],
            'handler'     => [Fixtures\Annotation\Route\Valid\MultipleMethodRouteController::class, 'default'],
            'middlewares' => [],
            'schemes'     => [],
            'defaults'    => [],
            'patterns'    => [],
            'arguments'   => [],
        ], Fixtures\Helper::routesToArray($routes));

        $this->assertContains([
            'name'        => 'flight_routing_tests_fixtures_annotation_route_valid_multiplemethodroutecontroller_default_1',
            'path'        => '/post',
            'domain'      => [],
            'methods'     => [Router::METHOD_POST],
            'handler'     => [Fixtures\Annotation\Route\Valid\MultipleMethodRouteController::class, 'default'],
            'middlewares' => [],
            'schemes'     => [],
            'defaults'    => [],
            'patterns'    => [],
            'arguments'   => [],
        ], Fixtures\Helper::routesToArray($routes));

        $this->assertContains([
            'name'        => 'flight_routing_tests_fixtures_annotation_route_valid_multiplemethodroutecontroller_default_2',
            'path'        => '/put',
            'domain'      => [],
            'methods'     => [Router::METHOD_PUT],
            'handler'     => [Fixtures\Annotation\Route\Valid\MultipleMethodRouteController::class, 'default'],
            'middlewares' => [],
            'schemes'     => [],
            'defaults'    => [],
            'patterns'    => [],
            'arguments'   => [],
        ], Fixtures\Helper::routesToArray($routes));

        $this->assertContains([
            'name'        => 'class_group@flight_routing_tests_fixtures_annotation_route_valid_multiplemethodroutecontroller_default',
            'path'        => '/get',
            'domain'      => [],
            'methods'     => [Router::METHOD_GET, Router::METHOD_HEAD, Router::METHOD_CONNECT],
            'handler'     => [Fixtures\Annotation\Route\Valid\MultipleMethodRouteController::class, 'default'],
            'middlewares' => [],
            'schemes'     => [],
            'defaults'    => [],
            'patterns'    => [],
            'arguments'   => [],
        ], Fixtures\Helper::routesToArray($routes));

        $this->assertContains([
            'name'        => 'class_group@flight_routing_tests_fixtures_annotation_route_valid_multiplemethodroutecontroller_default_1',
            'path'        => '/post',
            'domain'      => [],
            'methods'     => [Router::METHOD_POST, Router::METHOD_CONNECT],
            'handler'     => [Fixtures\Annotation\Route\Valid\MultipleMethodRouteController::class, 'default'],
            'middlewares' => [],
            'schemes'     => [],
            'defaults'    => [],
            'patterns'    => [],
            'arguments'   => [],
        ], Fixtures\Helper::routesToArray($routes));

        $this->assertContains([
            'name'        => 'class_group@flight_routing_tests_fixtures_annotation_route_valid_multiplemethodroutecontroller_default_2',
            'path'        => '/put',
            'domain'      => [],
            'methods'     => [Router::METHOD_PUT, Router::METHOD_CONNECT],
            'handler'     => [Fixtures\Annotation\Route\Valid\MultipleMethodRouteController::class, 'default'],
            'middlewares' => [],
            'schemes'     => [],
            'defaults'    => [],
            'patterns'    => [],
            'arguments'   => [],
        ], Fixtures\Helper::routesToArray($routes));

        $this->assertContains([
            'name'        => 'flight_routing_tests_fixtures_annotation_route_valid_methodonroutepattern',
            'path'        => 'testing/',
            'domain'      => [],
            'methods'     => [Router::METHOD_GET, Router::METHOD_HEAD],
            'handler'     => [Fixtures\Annotation\Route\Valid\MethodOnRoutePattern::class, 'handleSomething'],
            'middlewares' => [],
            'schemes'     => [],
            'defaults'    => [],
            'patterns'    => [],
            'arguments'   => [],
        ], Fixtures\Helper::routesToArray($routes));

        $this->assertContains([
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
        ], Fixtures\Helper::routesToArray($routes));

        $this->assertContains([
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
        ], Fixtures\Helper::routesToArray($routes));

        $this->assertContains([
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
        ], Fixtures\Helper::routesToArray($routes));

        $this->assertContains([
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
        ], Fixtures\Helper::routesToArray($routes));

        $this->assertContains([
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
        ], Fixtures\Helper::routesToArray($routes));

        $this->assertContains([
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
        ], Fixtures\Helper::routesToArray($routes));

        $this->assertContains([
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
        ], Fixtures\Helper::routesToArray($routes));

        $this->assertContains([
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
        ], Fixtures\Helper::routesToArray($routes));

        $this->assertContains([
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
        ], Fixtures\Helper::routesToArray($routes));

        $this->assertContains([
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
        ], Fixtures\Helper::routesToArray($routes));

        $this->assertContains([
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
        ], Fixtures\Helper::routesToArray($routes));

        $this->assertContains([
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
        ], Fixtures\Helper::routesToArray($routes));

        $this->assertContains([
            'name'        => 'user__restful',
            'path'        => '/user/{id:\d+}',
            'domain'      => [],
            'methods'     => Router::HTTP_METHODS_STANDARD,
            'handler'     => Fixtures\Annotation\Route\Valid\RestfulController::class,
            'middlewares' => [],
            'schemes'     => [],
            'defaults'    => [],
            'patterns'    => [],
            'arguments'   => [],
        ], Fixtures\Helper::routesToArray($routes));
    }

    /**
     * @runInSeparateProcess
     */
    public function testLoadAttribute(): void
    {
        $loader = new AnnotationLoader(new AttributeReader());

        $loader->attachListener(new Listener());
        $loader->attach(__DIR__ . '/../Fixtures/Annotation/Route/Attribute');

        $router = $this->getRouter();
        $router->loadAnnotation($loader);

        $routes = $router->getCollection()->getRoutes();

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
            'name'        => 'attribute_flight_routing_tests_fixtures_annotation_route_attribute_globaldefaultsclass_noname',
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
     * @runInSeparateProcess
     */
    public function testLoadWithAbstractClass(): void
    {
        $this->expectException('Biurad\Annotations\InvalidAnnotationException');
        $this->expectExceptionMessage(
            'Annotations from class "Flight\Routing\Tests\Fixtures\Annotation\Route\Abstracts\AbstractController"' .
            ' cannot be read as it is abstract.'
        );

        $loader = clone $this->loader;
        $loader->attach(__DIR__ . '/../Fixtures/Annotation/Route/Abstracts');

        $router = $this->getRouter();
        $router->loadAnnotation($loader);

        $router->getCollection()->getRoutes();
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
        $loader->attach($class);
        $router = $this->getRouter();

        // the given exception message should be tested through annotation class...
        $this->expectException(InvalidAnnotationException::class);
        $this->expectExceptionMessage($message);

        $router->loadAnnotation($loader);
        $router->getCollection()->getRoutes();
    }

    /**
     * @return string[]
     */
    public function invalidAnnotatedClasses(): array
    {
        return [
            [Fixtures\Annotation\Route\Invalid\DefaultsNotArray::class, '@Route.defaults must be an array.'],
            [Fixtures\Annotation\Route\Invalid\MethodsNotArray::class, '@Route.methods must contain only an array.'],
            [Fixtures\Annotation\Route\Invalid\MethodsNotStringable::class, '@Route.methods must contain only strings.'],
            [Fixtures\Annotation\Route\Invalid\MiddlewaresNotArray::class, '@Route.middlewares must be an array.'],
            [Fixtures\Annotation\Route\Invalid\MiddlewaresNotStringable::class, '@Route.middlewares must contain only strings.'],
            [Fixtures\Annotation\Route\Invalid\NameEmpty::class, '@Route.name must be not an empty string.'],
            [Fixtures\Annotation\Route\Invalid\NameNotString::class, '@Route.name must contain only a string.'],
            [Fixtures\Annotation\Route\Invalid\PathEmpty::class, '@Route.path must be not an empty string.'],
            [Fixtures\Annotation\Route\Invalid\PathNotString::class, '@Route.path must be not an empty string.'],
            [Fixtures\Annotation\Route\Invalid\PathMissing::class, '@Route.path must not be left empty.'],
        ];
    }
}
