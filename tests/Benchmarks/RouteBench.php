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

use Flight\Routing\Exceptions\UrlGenerationException;
use Flight\Routing\Generator\GeneratedUri;
use Flight\Routing\Route;
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

        yield 'Real Case' => ['/route/' . \rand(0, self::$maxRoutes)];

        yield 'Worst Case' => ['/route/399'];

        yield 'Domain Case' => ['//localhost.com/route/401'];

        yield 'Non-Existent Case' => ['/none'];
    }

    public function initUnoptimized(): void
    {
        $router = new Router();
        $router->setCollection(static function (RouteCollection $routes): void {
            $collection = [];

            for ($i = 1; $i <= self::$maxRoutes; ++$i) {
                $collection[] = Route::to("/route/{$i}", ['GET'])->bind('static_' . $i);
                $collection[] = Route::to("/route/{$i}/{foo}", ['GET'])->bind('no_static_' . $i);
            }

            $collection[] = Route::to("//localhost.com/route/401", ['GET'])->bind('static_' . 401);
            $collection[] = Route::to("//{host}/route/{foo}", ['GET'])->bind('no_static_' . 401);

            $routes->routes($collection);
        });

        $this->router = $router;
    }

    public function initOptimized(): void
    {
        $router = new Router(null, __DIR__ . '/compiled_test.php');
        $router->setCollection(static function (RouteCollection $routes): void {
            $collection = [];

            for ($i = 1; $i <= self::$maxRoutes; ++$i) {
                $collection[] = Route::to("/route/{$i}", ['GET'])->bind('static_' . $i);
                $collection[] = Route::to("/route/{$i}/{foo}", ['GET'])->bind('no_static_' . $i);
            }

            $collection[] = Route::to("//localhost.com/route/400", ['GET'])->bind('static_' . 401);
            $collection[] = Route::to("//{host}/route/{foo}", ['GET'])->bind('no_static_' . 401);

            $routes->routes($collection);
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

        assert($this->runScope($params[0], $result), new \RuntimeException(\sprintf('Route match failed, expected a route instance for "%s" request path.', $params[0])));
    }

    /**
     * @Groups(value={"dynamic_routes"})
     * @BeforeMethods({"initUnoptimized"}, extend=true)
     * @ParamProviders({"init"})
     */
    public function benchDynamicRoutes(array $params): void
    {
        $result = $this->router->match('GET', new Uri($params[0] . '/bar'));

        assert($this->runScope($params[0], $result), new \RuntimeException(\sprintf('Route match failed, expected a route instance for "%s" request path.', $params[0])));
    }

    /**
     * @Groups(value={"static_routes"})
     * @BeforeMethods({"initUnoptimized"}, extend=true)
     * @ParamProviders({"init"})
     */
    public function benchStaticRouteUri(array $params): void
    {
        $value = \substr($params[0], \stripos($params[0], '/') ?: -1);

        try {
            $result = $this->router->generateUri('static_' . $value);
            $result = $result instanceof GeneratedUri;
        } catch (\Throwable $e) {
            $result = '/none' === $params[0] ? $e instanceof UrlGenerationException : false;
        }

        \assert($result, new \RuntimeException(\sprintf('Route uri generation failed, for route name "%s" request path.', $value)));
    }

    /**
     * @Groups(value={"dynamic_routes"})
     * @BeforeMethods({"initUnoptimized"}, extend=true)
     * @ParamProviders({"init"})
     */
    public function benchDynamicRouteUri(array $params): void
    {
        $value = \substr($params[0], \stripos($params[0], '/') ?: -1);

        try {
            $result = $this->router->generateUri('no_static_' . $value, ['foo' => 'bar', 'host' => 'biurad.com']);
            $result = $result instanceof GeneratedUri;
        } catch (\Throwable $e) {
            $result = '/none' === $params[0] ? $e instanceof UrlGenerationException : false;
        }

        \assert($result, new \RuntimeException(\sprintf('Route uri generation failed, for route name "%s" request path.', $value)));
    }

    /**
     * @Groups(value={"cached_routes:static"})
     * @BeforeMethods({"initOptimized"}, extend=true)
     * @ParamProviders({"init"})
     */
    public function benchStaticCachedRoutes(array $params): void
    {
        $result = $this->router->match('GET', new Uri($params[0]));

        assert($this->runScope($params[0], $result), new \RuntimeException(\sprintf('Route match failed, expected a route instance for "%s" request path.', $params[0])));
    }

    /**
     * @Groups(value={"cached_routes:dynamic"})
     * @BeforeMethods({"initOptimized"}, extend=true)
     * @ParamProviders({"init"})
     */
    public function benchDynamicCachedRoutes(array $params): void
    {
        $result = $this->router->match('GET', new Uri($params[0] . '/bar'));

        assert($this->runScope($params[0], $result), new \RuntimeException(\sprintf('Route match failed, expected a route instance for "%s" request path.', $params[0])));
    }

    /**
     * @Groups(value={"optimized:static"})
     * @ParamProviders({"init"})
     */
    public function benchOptimizedStatic(array $params): void
    {
        $this->initOptimized();
        $result = $this->router->match('GET', new Uri($params[0]));

        assert($this->runScope($params[0], $result), new \RuntimeException(\sprintf('Route match failed, expected a route instance for "%s" request path.', $params[0])));
    }

    /**
     * @Groups(value={"optimized:dynamic"})
     * @ParamProviders({"init"})
     */
    public function benchOptimizedDynamic(array $params): void
    {
        $this->initOptimized();
        $result = $this->router->match('GET', new Uri($params[0]));

        assert($this->runScope($params[0], $result), new \RuntimeException(\sprintf('Route match failed, expected a route instance for "%s" request path.', $params[0])));
    }

    /**
     * @Groups(value={"unoptimized:static"})
     * @ParamProviders({"init"})
     */
    public function benchUnoptimizedStatic(array $params): void
    {
        $this->initUnoptimized();
        $result = $this->router->match('GET', new Uri($params[0]));

        assert($this->runScope($params[0], $result), new \RuntimeException(\sprintf('Route match failed, expected a route instance for "%s" request path.', $params[0])));
    }

    /**
     * @Groups(value={"unoptimized:dynamic"})
     * @ParamProviders({"init"})
     */
    public function benchUnoptimizedDynamic(array $params): void
    {
        $this->initUnoptimized();
        $result = $this->router->match('GET', new Uri($params[0]));

        assert($this->runScope($params[0], $result), new \RuntimeException(\sprintf('Route match failed, expected a route instance for "%s" request path.', $params[0])));
    }

    private function runScope(string $requestPath, ?Route $route): bool
    {
        if ($route instanceof Route) {
            return 'GET' === $route->getMethods()[0];
        } elseif ('/none' === $requestPath) {
            return null === $route;
        }

        return false;
    }
}
