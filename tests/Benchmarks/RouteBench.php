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

namespace Flight\Routing\Tests\Benchmarks;

use Flight\Routing\Routes\FastRoute as Route;
use Flight\Routing\RouteCollection;
use Flight\Routing\Router;
use Nyholm\Psr7\Uri;

/**
 * @Warmup(2)
 * @Revs(100)
 * @Iterations(5)
 * @BeforeClassMethods({"before"})
 */
class RouteBench
{
    private static int $maxRoutes = 400;

    private Router $router;

    public static function before(): void
    {
        if (\file_exists($cacheFile = __DIR__ . '/compiled_test.php')) {
            @unlink($cacheFile);
        }
    }

    /** @return \Generator<string,string> */
    public function init(): iterable
    {
        yield 'Best Case' => ['/route/1'];

        yield 'Average Case' => ['/route/199'];

        yield 'Real Case' => [\sprintf('/route/%d', \rand(0, 399))];

        yield 'Worst Case' => ['/route/399'];

        yield 'Non-Existent Case' => ['/none'];
    }

    public function initUnoptimized(): void
    {
        $router = new Router();
        $router->setCollection(static function (RouteCollection $routes): void {
            for ($i = 1; $i <= self::$maxRoutes; ++$i) {
                $routes->fastRoute("/route/{$i}", ['GET']);
                $routes->fastRoute("/route/{$i}/{foo}", ['GET']);
            }
        });

        $this->router = $router;
    }

    public function initOptimized(): void
    {
        $router = new Router(null, __DIR__ . '/compiled_test.php');
        $router->setCollection(static function (RouteCollection $routes): void {
            for ($i = 1; $i <= self::$maxRoutes; ++$i) {
                $routes->fastRoute("/route/{$i}", ['GET'])->bind('static_' . $i);
                $routes->fastRoute("/route/{$i}/{foo}", ['GET'])->bind('no_static_' . $i);
            }
        });

        $this->router = $router;
    }

    /**
     * @Groups(value={"static_routes"})
     * @BeforeMethods({"initUnoptimized"}, extend=true)
     * @ParamProviders({"init"})
     */
    public function benchStaticRoutes(array $params): void
    {
        $result = $this->router->match('GET', new Uri($params[0]));

        $this->runScope($params[0], $result);
    }

    /**
     * @Groups(value={"dynamic_routes"})
     * @BeforeMethods({"initUnoptimized"}, extend=true)
     * @ParamProviders({"init"})
     */
    public function benchDynamicRoutes(array $params): void
    {
        $result = $this->router->match('GET', new Uri($params[0] . '/bar'));

        $this->runScope($params[0], $result);
    }

    /**
     * @Groups(value={"cached_routes:static"})
     * @BeforeMethods({"initOptimized"}, extend=true)
     * @ParamProviders({"init"})
     */
    public function benchStaticCachedRoutes(array $params): void
    {
        $result = $this->router->match('GET', new Uri($params[0]));

        $this->runScope($params[0], $result);
    }

    /**
     * @Groups(value={"cached_routes:dynamic"})
     * @BeforeMethods({"initOptimized"}, extend=true)
     * @ParamProviders({"init"})
     */
    public function benchDynamicCachedRoutes(array $params): void
    {
        $result = $this->router->match('GET', new Uri($params[0] . '/bar'));

        $this->runScope($params[0], $result);
    }

    /**
     * @Groups(value={"optimized:static"})
     * @ParamProviders({"init"})
     */
    public function benchOptimizedStatic(array $params): void
    {
        $this->initOptimized();
        $result = $this->router->match('GET', new Uri($params[0]));

        $this->runScope($params[0], $result);
    }

    /**
     * @Groups(value={"optimized:dynamic"})
     * @ParamProviders({"init"})
     */
    public function benchOptimizedDynamic(array $params): void
    {
        $this->initOptimized();
        $result = $this->router->match('GET', new Uri($params[0]));

        $this->runScope($params[0], $result);
    }

    /**
     * @Groups(value={"unoptimized:static"})
     * @ParamProviders({"init"})
     */
    public function benchUnoptimizedStatic(array $params): void
    {
        $this->initUnoptimized();
        $result = $this->router->match('GET', new Uri($params[0]));

        $this->runScope($params[0], $result);
    }

    /**
     * @Groups(value={"unoptimized:dynamic"})
     * @ParamProviders({"init"})
     */
    public function benchUnoptimizedDynamic(array $params): void
    {
        $this->initUnoptimized();
        $result = $this->router->match('GET', new Uri($params[0]));

        $this->runScope($params[0], $result);
    }

    private function runScope(string $requestPath, ?Route $route): void
    {
        if ($route instanceof Route) {
            \assert('GET' === $route->getMethods()[0]);
        } elseif ('/none' === $requestPath) {
            \assert(null === $route);
        } else {
            throw new \RuntimeException(\sprintf('Route match failed, expected a route instance for "%s" request path.', $requestPath));
        }
    }
}
