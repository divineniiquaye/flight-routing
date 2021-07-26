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

namespace Flight\Routing;

use Fig\Http\Message\RequestMethodInterface;
use Flight\Routing\Interfaces\{RouteMapInterface, RouteMatcherInterface};
use Laminas\Stratigility\Next;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface, UriInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};

/**
 * Aggregate routes for matching and Dispatching.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class Router implements RouteMatcherInterface, RequestMethodInterface, MiddlewareInterface
{
    /**
     * Standard HTTP methods for browser requests.
     */
    public const HTTP_METHODS_STANDARD = [
        self::METHOD_HEAD,
        self::METHOD_GET,
        self::METHOD_POST,
        self::METHOD_PUT,
        self::METHOD_PATCH,
        self::METHOD_DELETE,
        self::METHOD_PURGE,
        self::METHOD_OPTIONS,
        self::METHOD_TRACE,
        self::METHOD_CONNECT,
    ];

    /** @var \SplQueue */
    private $pipeline;

    /** @var callable|null */
    private $collection;

    /** @var RouteMatcher */
    protected $matcher;

    /** @var CacheItemPoolInterface|string */
    private $cacheData;

    /**
     * @param CacheItemPoolInterface|string $cacheFile use file path or PSR-6 cache
     */
    public function __construct($cache = '')
    {
        $this->pipeline = new \SplQueue();
        $this->cacheData = $cache;
    }

    /**
     * {@inheritdoc}
     */
    public function match(string $method, UriInterface $uri): ?Route
    {
        return $this->getMatcher()->match($method, $uri);
    }

    /**
     * {@inheritdoc}
     */
    public function matchRequest(ServerRequestInterface $request): ?Route
    {
        return $this->getMatcher()->matchRequest($request);
    }

    /**
     * {@inheritdoc}
     */
    public function generateUri(string $routeName, array $parameters = []): GeneratedUri
    {
        return $this->getMatcher()->generateUri($routeName, $parameters);
    }

    /**
     * Attach middleware to the pipeline.
     */
    public function pipe(MiddlewareInterface ...$middlewares): void
    {
        foreach ($middlewares as $middleware) {
            $this->pipeline->enqueue($middleware);
        }
    }

    /**
     * Sets the RouteCollection instance associated with this Router.
     *
     * @param callable(RouteMapInterface) $routeDefinitionCallback
     */
    public function setCollection(callable $routeDefinitionCallback, $routeCollector = RouteCollection::class): void
    {
        $this->collection = static function () use ($routeDefinitionCallback, $routeCollector): RouteMapInterface {
            $routeCollector = new $routeCollector();
            \assert($routeCollector instanceof RouteMapInterface);

            $routeDefinitionCallback($routeCollector);

            return $routeCollector->getData();
        };
    }

    /**
     * If RouteCollection's data has been cached.
     */
    public function isCached(): bool
    {
        return ($this->cacheData instanceof CacheItemPoolInterface && $this->cacheData->hasItem(__FILE__)) || \file_exists($this->cacheData);
    }

    /**
     * Gets the Route matcher instance associated with this Router.
     */
    public function getMatcher(): RouteMatcher
    {
        if (null !== $this->matcher) {
            return $this->matcher;
        }

        if (null === $collection = $this->collection) {
            throw new \RuntimeException('A \'Flight\Routing\Interfaces\RouteMapInterface\' instance is missing in router, did you forget to set it.');
        }

        if (!empty($this->cacheData)) {
            $cachedData = self::getCachedData($this->cacheData, $collection);
        }

        return $this->matcher = new RouteMatcher($cachedData ?? $collection());
    }

    /**
     * {@inheritdoc}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $route = $this->getMatcher()->matchRequest($request);

        return (new Next($this->pipeline, $handler))->handle($request->withAttribute(Route::class, $route));
    }

    /**
     * @param CacheItemPoolInterface|string $cache
     * @param callable $loader
     */
    private static function getCachedData($cache, callable $loader): RouteMapInterface
    {
        if ($cache instanceof CacheItemPoolInterface) {
            $cachedData = $cache->getItem(__FILE__)->get();

            if (!$cachedData instanceof RouteMapInterface) {
                $cache->deleteItem(__FILE__);
                $cache->save($cache->getItem(__FILE__)->set($cachedData = $loader()));
            }

            return $cachedData;
        }

        $cachedData = @include $cache;

        if (!$cachedData instanceof RouteMapInterface) {
            $cachedData = $loader();

            if (!\is_dir($directory = \dirname($cache))) {
                @\mkdir($directory, 0775, true);
            }

            \file_put_contents($cache, "<?php // auto generated: AVOID MODIFYING\n\nreturn \unserialize('" . \serialize($cachedData) . "');\n");
        }

        return $cachedData;
    }
}
