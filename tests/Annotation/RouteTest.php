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
use Flight\Routing\Tests\Fixtures;

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

        $this->assertSame($params['name'], $route->name);
        $this->assertSame($params['path'], $route->path);
        $this->assertSame($params['methods'], $route->methods);

        // default property values...
        $this->assertSame([], $route->middlewares);
        $this->assertSame([], $route->defaults);
        $this->assertSame([], $route->patterns);
        $this->assertSame([], $route->schemes);
        $this->assertSame([], $route->domain);
    }

    public function testConstructorWithOptionalParams(): void
    {
        $params = [
            'name'         => 'foo',
            'value'        => '/foo',
            'methods'      => ['GET'],
            'domain'       => 'biurad.com',
            'middlewares'  => [Fixtures\BlankMiddleware::class],
            'defaults'     => ['foo' => 'bar'],
            'patterns'     => ['foo' => '[0-9]'],
            'schemes'      => ['https', 'http'],
        ];

        $route = new Route($params);

        $this->assertSame($params['name'], $route->name);
        $this->assertSame($params['value'], $route->path);
        $this->assertSame($params['methods'], $route->methods);
        $this->assertNotSame($params['domain'], $route->domain);
        $this->assertSame($params['middlewares'], $route->middlewares);
        $this->assertSame($params['defaults'], $route->defaults);
        $this->assertSame($params['patterns'], $route->patterns);
        $this->assertNotSame($params['schemes'], $route->schemes);
    }

    public function testConstructorParamsContainInvalidAttribute(): void
    {
        $this->expectException(InvalidAnnotationException::class);
        $this->expectExceptionMessage('The @Route.none-existing is unsupported. Allowed param keys are ["path", "name", "resource", "patterns", "defaults", "methods", "domain", "schemes", "middlewares"].');

        new Route(['none-existing' => 'something']);
    }

    public function testConstructorParamsContainNameInvalid(): void
    {
        $this->expectException(InvalidAnnotationException::class);
        $this->expectExceptionMessage('@Route.name must contain only a type of string.');

        new Route(['path' => '/foo', 'name' => 23, 'methods' => ['GET']]);
    }

    public function testConstructorParamsNotContainPath(): void
    {
        $route = new Route([
            'name'    => 'foo',
            'methods' => ['GET'],
        ]);

        $this->assertNull($route->path);
    }

    public function testConstructorParamsContainStringPath(): void
    {
        $route = new Route('/hello');

        $this->assertNull($route->name);
        $this->assertSame('/hello', $route->path);
        $this->assertEmpty($route->methods);

        // default property values...
        $this->assertSame([], $route->middlewares);
        $this->assertSame([], $route->defaults);
        $this->assertSame([], $route->patterns);
        $this->assertSame([], $route->schemes);
        $this->assertSame([], $route->domain);
    }

    public function testConstructorParamsContainStringMethods(): void
    {
        $this->expectException(InvalidAnnotationException::class);
        $this->expectExceptionMessage('@Route.methods must contain only an array list of strings.');

        new Route(['name' => 'foo', 'methods' => [['GET']], 'path' => '/foo']);
    }

    public function testConstructorParamsContainNullName(): void
    {
        $route = new Route(['path' => '/foo', 'methods' => ['GET']]);
        $this->assertSame(null, $route->name);
    }

    /**
     * @dataProvider invalidDataProviderIfStringExpected
     *
     * @param mixed $invalidName
     */
    public function testConstructorParamsContainInvalidName($invalidName): void
    {
        $this->expectException(InvalidAnnotationException::class);
        $this->expectExceptionMessage('@Route.name must contain only a type of string.');

        new Route(['name' => $invalidName, 'path' => '/foo', 'methods' => ['GET']]);
    }

    /**
     * @dataProvider invalidDataProviderIfStringExpected
     *
     * @param mixed $invalidPath
     */
    public function testConstructorParamsContainInvalidPath($invalidPath): void
    {
        $this->expectException(InvalidAnnotationException::class);
        $this->expectExceptionMessage('@Route.path must contain only a type of string.');

        new Route(['name' => 'foo', 'path' => $invalidPath, 'methods' => ['GET']]);
    }

    /**
     * @dataProvider invalidDataProviderIfArrayExpected
     *
     * @param mixed $invalidMethods
     */
    public function testConstructorParamsContainInvalidMethods($invalidMethods): void
    {
        $this->expectException(InvalidAnnotationException::class);
        $this->expectExceptionMessage('@Route.methods must contain only an array list of strings.');

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
        $this->expectExceptionMessage('@Route.middlewares must contain only an array list of strings.');

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
        $this->expectExceptionMessage('@Route.defaults must contain a sequence array of string keys and values. eg: [key => value]');

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
        $this->expectExceptionMessage('@Route.patterns must contain a sequence array of string keys and values. eg: [key => value]');

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
        $this->expectExceptionMessage('@Route.schemes must contain only an array list of strings.');

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
        $this->expectExceptionMessage('@Route.methods must contain only an array list of strings.');

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
        $this->expectExceptionMessage('@Route.patterns must contain a sequence array of string keys and values. eg: [key => value]');

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
        $this->expectExceptionMessage('@Route.schemes must contain only an array list of strings.');

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
        $this->expectExceptionMessage('@Route.middlewares must contain only an array list of strings.');

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
            [true],
            [false],
            [0],
            [0.0],
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
            [true],
            [false],
            [0.0],
            [[]],
            [new \stdClass()],
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
            [true],
            [false],
            [0],
            [0.0],
            [[]],
            [new \stdClass()],
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
            [true],
            [false],
            [0],
            [0.0],
            [new \stdClass()],
            [function (): void {
            }],
            [\STDOUT],
        ];
    }
}
