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

use Biurad\Annotations\InvalidAnnotationException;
use Flight\Routing\Annotation\Route;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * RouteTest
 */
class RouteTest extends TestCase
{
    public function testConstructor(): void
    {
        $params = [
            'name'     => 'foo',
            'path'     => '/foo',
            'methods'  => ['GET'],
        ];

        $route = new Route($params);

        $this->assertSame($params['name'], $route->getName());
        $this->assertSame($params['path'], $route->getPath());
        $this->assertSame($params['methods'], $route->getMethods());

        // default property values...
        $this->assertSame([], $route->getMiddlewares());
        $this->assertSame([], $route->getDefaults());
        $this->assertSame([], $route->getPatterns());
        $this->assertSame([], $route->getSchemes());
        $this->assertSame(null, $route->getDomain());
    }

    public function testConstructorWithOptionalParams(): void
    {
        $params = [
            'name'         => 'foo',
            'value'        => '/foo',
            'methods'      => ['GET'],
            'domain'       => 'biurad.com',
            'middlewares'  => [Fixture\BlankMiddleware::class],
            'defaults'     => ['foo' => 'bar'],
            'patterns'     => ['foo' => '[0-9]'],
            'schemes'      => ['https', 'http'],
        ];

        $route = new Route($params);

        $this->assertSame($params['name'], $route->getName());
        $this->assertSame($params['value'], $route->getPath());
        $this->assertSame($params['methods'], $route->getMethods());
        $this->assertSame($params['domain'], $route->getDomain());
        $this->assertSame($params['middlewares'], $route->getMiddlewares());
        $this->assertSame($params['defaults'], $route->getDefaults());
        $this->assertSame($params['patterns'], $route->getPatterns());
        $this->assertSame($params['schemes'], $route->getSchemes());
    }

    public function testConstructorParamsContainNameInvalid(): void
    {
        $this->expectException(InvalidAnnotationException::class);
        $this->expectExceptionMessage('@Route.name must contain only a string.');

        new Route(['path' => '/foo', 'name' => 23, 'methods' => ['GET']]);
    }

    public function testConstructorParamsNotContainPath(): void
    {
        $route = new Route([
            'name'    => 'foo',
            'methods' => ['GET'],
        ]);

        $this->assertNull($route->getPath());
    }

    public function testConstructorParamsContainStringMethods(): void
    {
        $this->expectException(InvalidAnnotationException::class);
        $this->expectExceptionMessage('@Route.methods must contain only an array.');

        new Route(['name' => 'foo', 'methods' => 'GET', 'path' => '/foo']);
    }

    public function testConstructorParamsContainEmptyName(): void
    {
        $this->expectException(InvalidAnnotationException::class);
        $this->expectExceptionMessage('@Route.name must be not an empty string.');

        new Route(['name' => '', 'path' => '/foo', 'methods' => ['GET']]);
    }

    public function testConstructorParamsContainEmptyPath(): void
    {
        $this->expectException(InvalidAnnotationException::class);
        $this->expectExceptionMessage('@Route.path must be not an empty string.');

        new Route(['name' => 'foo', 'path' => '', 'methods' => ['GET']]);
    }

    public function testConstructorParamsContainNullName(): void
    {
        $route = new Route(['path' => '/foo', 'methods' => ['GET']]);
        $this->assertSame(null, $route->getName());
    }

    /**
     * @dataProvider invalidDataProviderIfStringExpected
     *
     * @param mixed $invalidName
     */
    public function testConstructorParamsContainInvalidName($invalidName): void
    {
        $this->expectException(InvalidAnnotationException::class);
        $this->expectExceptionMessage('@Route.name must');

        new Route(['name' => $invalidName ?? '', 'path' => '/foo', 'methods' => ['GET']]);
    }

    /**
     * @dataProvider invalidDataProviderIfStringExpected
     *
     * @param mixed $invalidPath
     */
    public function testConstructorParamsContainInvalidPath($invalidPath): void
    {
        $this->expectException(InvalidAnnotationException::class);
        $this->expectExceptionMessage('@Route.path must be not an empty string.');

        new Route(['name' => 'foo', 'path' => $invalidPath ?? '', 'methods' => ['GET']]);
    }

    /**
     * @dataProvider invalidDataProviderIfArrayExpected
     *
     * @param mixed $invalidMethods
     */
    public function testConstructorParamsContainInvalidMethods($invalidMethods): void
    {
        $this->expectException(InvalidAnnotationException::class);
        $this->expectExceptionMessage('@Route.methods must contain only an array.');

        new Route(['name' => 'foo', 'path' => '/foo', 'methods' => $invalidMethods]);
    }

    /**
     * @dataProvider invalidDataProviderIfArrayExpected
     *
     * @param mixed $invalidMiddlewares
     */
    public function testConstructorParamsContainInvalidMiddlewares($invalidMiddlewares): void
    {
        $this->expectException(InvalidAnnotationException::class);
        $this->expectExceptionMessage('@Route.middlewares must be an array.');

        new Route([
            'name'        => 'foo',
            'path'        => '/foo',
            'methods'     => ['GET'],
            'middlewares' => $invalidMiddlewares,
        ]);
    }

    /**
     * @dataProvider invalidDataProviderIfArrayExpected
     *
     * @param mixed $invalidDefaults
     */
    public function testConstructorParamsContainInvalidDefaults($invalidDefaults): void
    {
        $this->expectException(InvalidAnnotationException::class);
        $this->expectExceptionMessage('@Route.defaults must be an array.');

        new Route([
            'name'     => 'foo',
            'path'     => '/foo',
            'methods'  => ['GET'],
            'defaults' => $invalidDefaults,
        ]);
    }

    /**
     * @dataProvider invalidDataProviderIfArrayExpected
     *
     * @param mixed $invalidPatterns
     */
    public function testConstructorParamsContainInvalidPatterns($invalidPatterns): void
    {
        $this->expectException(InvalidAnnotationException::class);
        $this->expectExceptionMessage('@Route.patterns must be an array.');

        new Route([
            'name'     => 'foo',
            'path'     => '/foo',
            'methods'  => ['GET'],
            'patterns' => $invalidPatterns,
        ]);
    }

    /**
     * @dataProvider invalidDataProviderIfArrayExpected
     *
     * @param mixed $invalidSchemes
     */
    public function testConstructorParamsContainInvalidSchemes($invalidSchemes): void
    {
        $this->expectException(InvalidAnnotationException::class);
        $this->expectExceptionMessage('@Route.schemes must be an array.');

        new Route([
            'name'       => 'foo',
            'path'       => '/foo',
            'methods'    => ['GET'],
            'schemes'    => $invalidSchemes,
        ]);
    }

    /**
     * @dataProvider invalidDataProviderIfStringExpected
     *
     * @param mixed $invalidMethod
     */
    public function testConstructorMethodsParamContainsInvalidValue($invalidMethod): void
    {
        $this->expectException(InvalidAnnotationException::class);
        $this->expectExceptionMessage('@Route.methods must contain only strings.');

        new Route([
            'name'    => 'foo',
            'path'    => '/foo',
            'methods' => [$invalidMethod],
        ]);
    }

    /**
     * @dataProvider invalidDataProviderIfStringExpected
     *
     * @param mixed $invalidPatterns
     */
    public function testConstructorPatternsParamContainsInvalidValue($invalidPatterns): void
    {
        $this->expectException(InvalidAnnotationException::class);
        $this->expectExceptionMessage('@Route.patterns must contain only strings.');

        new Route([
            'name'        => 'foo',
            'path'        => '/foo',
            'methods'     => ['GET'],
            'patterns'    => [$invalidPatterns],
        ]);
    }

    /**
     * @dataProvider invalidDataProviderIfStringExpected
     *
     * @param mixed $invalidSchemes
     */
    public function testConstructorSchemesParamContainsInvalidValue($invalidSchemes): void
    {
        $this->expectException(InvalidAnnotationException::class);
        $this->expectExceptionMessage('@Route.schemes must contain only strings.');

        new Route([
            'name'        => 'foo',
            'path'        => '/foo',
            'methods'     => ['GET'],
            'schemes'     => [$invalidSchemes],
        ]);
    }

    /**
     * @dataProvider invalidDataProviderIfStringExpected
     *
     * @param mixed $invalidMiddleware
     */
    public function testConstructorMiddlewaresParamContainsInvalidValue($invalidMiddleware): void
    {
        $this->expectException(InvalidAnnotationException::class);
        $this->expectExceptionMessage('@Route.middlewares must contain only strings.');

        new Route([
            'name'        => 'foo',
            'path'        => '/foo',
            'methods'     => ['GET'],
            'middlewares' => [$invalidMiddleware],
        ]);
    }

    /**
     * @return string[]
     */
    public function invalidDataProviderIfArrayExpected(): array
    {
        return [
            [null],
            [true],
            [false],
            [0],
            [0.0],
            [''],
            [new stdClass()],
            [function (): void {
            }],
            [\STDOUT],
        ];
    }

    /**
     * @return string[]
     */
    public function invalidDataProviderIfIntegerExpected(): array
    {
        return [
            [null],
            [true],
            [false],
            [0.0],
            [''],
            [[]],
            [new stdClass()],
            [function (): void {
            }],
            [\STDOUT],
        ];
    }

    /**
     * @return string[]
     */
    public function invalidDataProviderIfStringExpected(): array
    {
        return [
            [null],
            [true],
            [false],
            [0],
            [0.0],
            [[]],
            [new stdClass()],
            [function (): void {
            }],
            [\STDOUT],
        ];
    }

    /**
     * @return string[]
     */
    public function invalidDataProviderIfArrayOrStringExpected(): array
    {
        return [
            [null],
            [true],
            [false],
            [0],
            [0.0],
            [new stdClass()],
            [function (): void {
            }],
            [\STDOUT],
        ];
    }
}
