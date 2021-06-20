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
use Flight\Routing\Interfaces\RouteCompilerInterface;
use Laminas\Stratigility\{MiddlewarePipe, MiddlewarePipeInterface};
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};

/**
 * Aggregate routes for matching and Dispatching.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class Router extends RouteMatcher implements \IteratorAggregate, RequestMethodInterface, MiddlewareInterface
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

    /** @var DebugRoute|null */
    private $debug;

    public function __construct(
        RouteCollection $collection,
        ?RouteCompilerInterface $compiler = null,
        ?MiddlewarePipeInterface $dispatcher = null
    ) {
        parent::__construct($collection->getIterator(), $compiler);

        // Add Middleware support.
        $this->pipeline = $dispatcher ?? new MiddlewarePipe();

        // Enable routes profiling ...
        $this->debug = $collection->getDebugRoute();
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
    public function match(RequestContext $requestContext): ?Route
    {
        $route = parent::match($requestContext);

        if ($route instanceof Route && null !== $this->debug) {
            $this->debug->setMatched($route->get('name'));
        }

        return $route;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $route = $this->matchRequest($request);

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
