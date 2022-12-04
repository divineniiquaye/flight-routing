<?php declare(strict_types=1);

/*
 * This file is part of Flight Routing.
 *
 * PHP version 8.0 and above required
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 Divine Niiquaye Ibok (https://divinenii.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flight\Routing\Tests\Benchmarks;

use Flight\Routing\Exceptions\MethodNotAllowedException;
use Flight\Routing\Interfaces\RouteMatcherInterface;
use Flight\Routing\{RouteCollection, Router};

/**
 * @Groups({"performance"})
 */
final class PerformanceBench extends RouteBench
{
    /**
     * {@inheritdoc}
     */
    public function createDispatcher(string $cache = null): RouteMatcherInterface
    {
        $router = new Router(null, $cache);
        $router->setCollection(static function (RouteCollection $routes): void {
            for ($i = 0; $i < self::MAX_ROUTES; ++$i) {
                if (199 === $i) {
                    $routes->add('//localhost.com/route'.$i, ['GET'])->bind('static-'.$i);
                    $routes->add('//{host}/route{foo}/'.$i, ['GET'])->bind('no-static-'.$i);
                    continue;
                }

                $routes->add('/route'.$i, ['GET'])->bind('static-'.$i);
                $routes->add('/route{foo}/'.$i, ['GET'])->bind('no-static-'.$i);
            }
        });

        return $router;
    }

    /**
     * {@inheritdoc}
     */
    public function provideStaticRoutes(): iterable
    {
        yield 'first' => [
            'method' => 'GET',
            'route' => '/route0',
            'result' => ['handler' => null, 'prefix' => '/route0', 'path' => '/route0', 'methods' => ['GET' => true], 'name' => 'static-0'],
        ];

        yield 'middle' => [
            'method' => 'GET',
            'route' => '//localhost/route199',
            'result' => ['handler' => null, 'hosts' => ['localhost' => true], 'prefix' => '/route199', 'path' => '/route199', 'methods' => ['GET' => true], 'name' => 'static-199'],
        ];

        yield 'last' => [
            'method' => 'GET',
            'route' => '/route399',
            'result' => ['handler' => null, 'prefix' => '/route399', 'path' => '/route399', 'methods' => ['GET' => true], 'name' => 'static-399'],
        ];

        yield 'invalid-method' => [
            'method' => 'PUT',
            'route' => '/route399',
            'result' => MethodNotAllowedException::class,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function provideDynamicRoutes(): iterable
    {
        yield 'first' => [
            'method' => 'GET',
            'route' => '/routebar/0',
            'result' => ['handler' => null, 'prefix' => '/route', 'path' => '/route{foo}/0', 'methods' => ['GET' => true], 'name' => 'not-static-0', ['arguments' => ['foo' => 'bar']]],
        ];

        yield 'middle' => [
            'method' => 'GET',
            'route' => '//localhost/routebar/199',
            'result' => ['handler' => null, 'hosts' => ['{host}' => true], 'prefix' => '/route', 'path' => '/route{foo}/199', 'methods' => ['GET' => true], 'name' => 'not-static-199', ['arguments' => ['foo' => 'bar']]],
        ];

        yield 'last' => [
            'method' => 'GET',
            'route' => '/routebar/399',
            'result' => ['handler' => null, 'prefix' => '/route', 'path' => '/route{foo}/399', 'methods' => ['GET' => true], 'name' => 'not-static-399', ['arguments' => ['foo' => 'bar']]],
        ];

        yield 'invalid-method' => [
            'method' => 'PUT',
            'route' => '/routebar/399',
            'result' => MethodNotAllowedException::class,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function provideOtherScenarios(): iterable
    {
        yield 'non-existent' => [
            'method' => 'GET',
            'route' => '/testing',
            'result' => null,
        ];
    }
}
