<?php

declare(strict_types=1);

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
use Nyholm\Psr7\Uri;

/**
 * @Warmup(2)
 * @Revs(100)
 * @Iterations(5)
 * @BeforeClassMethods({"before"})
 */
abstract class RouteBench
{
    protected const MAX_ROUTES = 400;
    protected const CACHE_FILE = __DIR__.'/../Fixtures/compiled_test.php';

    /** @var array<string,RouteMatcherInterface> */
    private array $dispatchers = [];

    public static function before(): void
    {
        if (\file_exists(self::CACHE_FILE)) {
            @\unlink(self::CACHE_FILE);
        }
    }

    /** @return \Generator<string,array<string,mixed>> */
    abstract public function provideStaticRoutes(): iterable;

    /** @return \Generator<string,array<string,mixed>> */
    abstract public function provideDynamicRoutes(): iterable;

    /** @return \Generator<string,array<string,mixed>> */
    abstract public function provideOtherScenarios(): iterable;

    /** @return \Generator<string,array<int,mixed>> */
    public function provideAllScenarios(): iterable
    {
        yield 'static(first,middle,last,invalid-method)' => \array_values(\iterator_to_array($this->provideStaticRoutes()));
        yield 'dynamic(first,middle,last,invalid-method)' => \array_values(\iterator_to_array($this->provideDynamicRoutes()));
        yield 'others(non-existent,...)' => \array_values(\iterator_to_array($this->provideOtherScenarios()));
    }

    /** @return \Generator<string,array<string,string>> */
    public function provideDispatcher(): iterable
    {
        yield 'not_cached' => ['dispatcher' => 'not_cached'];
        yield 'cached' => ['dispatcher' => 'cached'];
    }

    public function initDispatchers(): void
    {
        $this->dispatchers['not_cached'] = $this->createDispatcher();
        $this->dispatchers['cached'] = $this->createDispatcher(self::CACHE_FILE);
    }

    /**
     * @BeforeMethods({"initDispatchers"})
     * @ParamProviders({"provideDispatcher", "provideStaticRoutes"})
     */
    public function benchStaticRoutes(array $params): void
    {
        $this->runScenario($params);
    }

    /**
     * @BeforeMethods({"initDispatchers"})
     * @ParamProviders({"provideDispatcher", "provideDynamicRoutes"})
     */
    public function benchDynamicRoutes(array $params): void
    {
        $this->runScenario($params);
    }

    /**
     * @BeforeMethods({"initDispatchers"})
     * @ParamProviders({"provideDispatcher", "provideOtherScenarios"})
     */
    public function benchOtherRoutes(array $params): void
    {
        $this->runScenario($params);
    }

    /**
     * @BeforeMethods({"initDispatchers"})
     * @ParamProviders({"provideDispatcher", "provideAllScenarios"})
     */
    public function benchAll(array $params): void
    {
        $dispatcher = \array_shift($params);

        foreach ($params as $param) {
            $this->runScenario($param + \compact('dispatcher'));
        }
    }

    /**
     * @ParamProviders({"provideAllScenarios"})
     * @Revs(4)
     */
    public function benchWithRouter(array $params): void
    {
        $this->dispatchers['router'] = $this->createDispatcher();

        foreach ($params as $param) {
            $this->runScenario($param + ['dispatcher' => 'router']);
        }
    }

    /**
     * @ParamProviders({"provideAllScenarios"})
     * @Revs(4)
     */
    public function benchWithCache(array $params): void
    {
        $this->dispatchers['cached'] = $this->createDispatcher(self::CACHE_FILE);

        foreach ($params as $param) {
            $this->runScenario($param + ['dispatcher' => 'cached']);
        }
    }

    abstract protected function createDispatcher(string $cache = null): RouteMatcherInterface;

    /**
     * @param array<string,array<int,mixed>|string> $params
     */
    private function runScenario(array $params): void
    {
        try {
            $dispatcher = $this->dispatchers[$params['dispatcher']];
            $result = $params['result'] === $dispatcher->match($params['method'], new Uri($params['route']));
        } catch (MethodNotAllowedException $e) {
            $result = $params['result'] === $e::class;
        }

        \assert($result, new \RuntimeException(
            \sprintf(
                'Benchmark "%s: %s" failed with method "%s"',
                $params['dispatcher'],
                $params['route'],
                $params['method']
            )
        ));
    }
}
