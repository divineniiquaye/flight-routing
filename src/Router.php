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
use Flight\Routing\Handlers\RouteHandler;
use Flight\Routing\Interfaces\RouteCompilerInterface;
use Laminas\Stratigility\{MiddlewarePipe, MiddlewarePipeInterface};
use Psr\Http\Message\{ResponseFactoryInterface, ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};

/**
 * Aggregate routes for matching and Dispatching.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class Router extends RouteMatcher implements \IteratorAggregate, RequestMethodInterface, RequestHandlerInterface
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

    /** @var ResponseFactoryInterface */
    private $responseFactory;

    /** @var DebugRoute|null */
    private $debug;

    /** @var null|callable(mixed,array) */
    private $handlerResolver = null;

    public function __construct(
        ResponseFactoryInterface $responseFactory,
        ?RouteCompilerInterface $compiler = null,
        ?string $cacheFile = null,
        bool $debug = false
    ) {
        parent::__construct(new \ArrayIterator(), $compiler, $cacheFile);

        // Add Middleware support.
        $this->pipeline = new MiddlewarePipe();
        $this->responseFactory = $responseFactory;

        // Enable routes profiling ...
        $this->debug = $debug ? new DebugRoute() : null;
    }

    /**
     * Set the route handler resolver.
     *
     * @param null|callable(mixed:$handler,array:$arguments) $handlerResolver
     */
    public function setHandlerResolver(?callable $handlerResolver): void
    {
        $this->handlerResolver = $handlerResolver;
    }

    /**
     * Adds the given route(s) to the router.
     *
     * @param Route ...$routes
     */
    public function addRoute(Route ...$routes): void
    {
        foreach ($routes as $route) {
            if (null === $name = $route->get('name')) {
                $route->bind($name = $route->generateRouteName(''));
            }

            if (null !== $this->debug) {
                $this->debug->addProfile($name, $route);
            }

            $this->routes[] = $route;
        }
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
     * {@inheritdoc}
     *
     * @return \ArrayIterator<int,Route>|\ArrayIterator<int,array>
     */
    public function getIterator(): \ArrayIterator
    {
        return $this->routes;
    }

    /**
     * {@inheritdoc}
     */
    public function match(ServerRequestInterface $request): ?Route
    {
        $route = parent::match($request);

        if ($route instanceof Route && null !== $this->debug) {
            $this->debug->setMatched($route->get('name'));
        }

        return $route;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $route = $this->match($request);
        $handler = new RouteHandler($this->responseFactory, $this->handlerResolver);

        if (null !== $route && !empty($routeMiddlewares = $route->get('middlewares'))) {
            $this->pipe(...$routeMiddlewares);
        }

        try {
            return $this->pipeline->process($request->withAttribute(Route::class, $route), $handler);
        } finally {
            if (null !== $this->debug) {
                $this->debug->leave();
            }
        }
    }

    /**
     * Get the profiled routes.
     */
    public function getProfile(): ?DebugRoute
    {
        return $this->debug;
    }
}
