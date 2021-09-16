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
use Flight\Routing\Generator\GeneratedUri;
use Flight\Routing\Routes\FastRoute as Route;
use Flight\Routing\Interfaces\{RouteCompilerInterface, RouteMatcherInterface};
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

    /** @var RouteCollection|callable|null */
    private $collection;

    /** @var RouteCompilerInterface|null */
    private $compiler;

    /** @var RouteMatcherInterface */
    private $matcher;

    /** @var CacheItemPoolInterface|string */
    private $cacheData;

    /**
     * @param CacheItemPoolInterface|string $cache use file path or PSR-6 cache
     */
    public function __construct(RouteCompilerInterface $compiler = null, $cache = '')
    {
        $this->compiler = $compiler;
        $this->pipeline = new \SplQueue();
        $this->cacheData = $cache;
    }

    /**
     * Set a route collection instance into Router in order to use addRoute method.
     *
     * @param CacheItemPoolInterface|string $cache use file path or PSR-6 cache
     */
    public static function withCollection(?RouteCollection $collection = null, RouteCompilerInterface $compiler = null, $cache = ''): static
    {
        $new = new static($compiler, $cache);
        $new->collection = $collection ?? new RouteCollection();

        return $new;
    }

    /**
     * This method works only if withCollection method is used.
     */
    public function addRoute(Route ...$routes): void
    {
        if ($this->collection instanceof RouteCollection) {
            $this->collection->add(...$routes);
        }
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
     * @param callable $routeDefinitionCallback takes only one parameter of route collection.
     */
    public function setCollection(callable $routeDefinitionCallback): void
    {
        $this->collection = static function () use ($routeDefinitionCallback): RouteCollection {
            $routeDefinitionCallback($routeCollector = new RouteCollection());

            return $routeCollector;
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
    public function getMatcher(): RouteMatcherInterface
    {
        if (null !== $this->matcher) {
            return $this->matcher;
        }

        if (null === $collection = $this->collection) {
            throw new \RuntimeException(\sprintf('Did you forget to set add the route collection with the "%s".', __CLASS__ . '::setCollection'));
        }

        if ($collection instanceof RouteCollection) {
            $collection = static function () use ($collection): RouteCollection {
                return $collection;
            };
        }

        if (!empty($this->cacheData)) {
            $matcher = $this->getCachedData($this->cacheData, $collection);
        }

        return $this->matcher = $matcher ?? new RouteMatcher($collection(), $this->compiler);
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
     */
    protected function getCachedData($cache, callable $loader): RouteMatcherInterface
    {
        if ($cache instanceof CacheItemPoolInterface) {
            $cachedData = $cache->getItem(__FILE__)->get();

            if (!$cachedData instanceof RouteMatcherInterface) {
                $cache->deleteItem(__FILE__);
                $cache->save($cache->getItem(__FILE__)->set($cachedData = new RouteMatcher($loader(), $this->compiler)));
            }

            return $cachedData;
        }

        $cachedData = @include $cache;

        if (!$cachedData instanceof RouteMatcherInterface) {
            $cachedData = new RouteMatcher($loader(), $this->compiler);

            if (!\is_dir($directory = \dirname($cache))) {
                @\mkdir($directory, 0775, true);
            }

            \file_put_contents($cache, "<?php // auto generated: AVOID MODIFYING\n\nreturn \unserialize('" . \serialize($cachedData) . "');\n");
        }

        return $cachedData;
    }
}
