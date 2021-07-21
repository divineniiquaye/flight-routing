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
use Flight\Routing\Interfaces\RouteMatcherInterface;
use Laminas\Stratigility\{MiddlewarePipe, MiddlewarePipeInterface};
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

    /** @var MiddlewarePipeInterface */
    private $pipeline;

    /** @var RouteCollection|null */
    private $collection;

    /** @var RouteMatcher */
    protected $matcher;

    /** @var CacheItemPoolInterface|string */
    private $cacheData;

    /** @var bool */
    private $hasCached;

    /**
     * @param CacheItemPoolInterface|string $cacheFile use file path or PSR-6 cache
     */
    public function __construct(MiddlewarePipeInterface $dispatcher = null, $cache = '')
    {
        $this->pipeline = $dispatcher ?? new MiddlewarePipe();

        $this->hasCached = ($cache instanceof CacheItemPoolInterface && $cache->hasItem(__FILE__)) || \file_exists($cache);
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
            $this->pipeline->pipe($middleware);
        }
    }

    /**
     * Sets the RouteCollection instance associated with this Router.
     */
    public function setCollection(RouteCollection $collection): void
    {
        $this->collection = $collection;
    }

    /**
     * Gets the RouteCollection instance associated with this Router.
     *
     * WARNING: This method should never be used at runtime as it is SLOW.
     *          You might use it in a cache warmer though.
     */
    public function getCollection(): RouteCollection
    {
        if (null === $this->collection) {
            throw new \RuntimeException('A RouteCollection instance is missing in router, did you forget to set it.');
        }

        return $this->collection;
    }

    /**
     * If RouteCollection's data has been cached.
     */
    public function isCached(): bool
    {
        return $this->hasCached;
    }

    /**
     * Gets the Route matcher instance associated with this Router.
     */
    public function getMatcher(): RouteMatcher
    {
        if (isset($this->matcher)) {
            return $this->matcher;
        }

        if ($this->hasCached) {
            return $this->matcher = new RouteMatcher(self::getCachedData($this->cacheData));
        }

        if ('' === $cache = $this->cacheData) {
            default_matcher:
            return $this->matcher = new RouteMatcher($this->getCollection());
        }

        $collection = $this->getCollection();
        $collectionData = $collection->getData();

        if ($cache instanceof CacheItemPoolInterface) {
            $cache->save($cache->getItem(__FILE__)->set([$collection->getCompiler(), $collectionData]));
        } else {
            $cachedData = \serialize([$collection->getCompiler(), $collectionData]);

            if (!\is_dir($directory = \dirname($cache))) {
                @\mkdir($directory, 0775, true);
            }

            \file_put_contents($cache, "<?php // auto generated: AVOID MODIFYING\n\nreturn new Flight\Routing\CachedData(\unserialize('" . $cachedData . "'));\n");
        }

        goto default_matcher;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $route = $this->getMatcher()->matchRequest($request);

        if (null !== $route && !empty($routeMiddlewares = $route->get('middlewares'))) {
            $this->pipe(...$routeMiddlewares);
        }

        return $this->pipeline->process($request->withAttribute(Route::class, $route), $handler);
    }

    /**
     * @param CacheItemPoolInterface|string $cache
     */
    private static function getCachedData($cache): CachedData
    {
        if ($cache instanceof CacheItemPoolInterface) {
            $cachedData = $cache->getItem(__FILE__)->get();

            if (!$cachedData instanceof CachedData) {
                $cache->deleteItem(__FILE__);

                throw new \RuntimeException('Failed to fetch cached routes data from PRS-6 cache pool, try reloading.');
            }

            return $cachedData;
        }

        $cachedData = require $cache;

        if (!$cachedData instanceof CachedData) {
            @\unlink($cache);

            throw new \RuntimeException(\sprintf('Failed to fetch cached routes data from "%s" file, try reloading.', $cache));
        }

        return $cachedData;
    }
}
