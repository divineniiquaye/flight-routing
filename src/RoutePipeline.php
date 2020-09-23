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

use Flight\Routing\Exceptions\InvalidMiddlewareException;
use Flight\Routing\Exceptions\RouteNotFoundException;
use Flight\Routing\Interfaces\RouteInterface;
use Laminas\Stratigility\MiddlewarePipe;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Marshal middleware for use in the application.
 *
 * This class provides a number of methods for preparing and returning
 * middleware for use within an application.
 *
 * Middleware are organized as a stack. That means middleware
 * that have been added before will be executed after the newly
 * added one (last in, first out).
 *
 * If any middleware provided is already a MiddlewareInterface, it can be used
 * verbatim or decorated as-is. Other middleware types acceptable are:
 *
 * - PSR-15 RequestHandlerInterface instances; these will be decorated as
 *   RequestHandlerMiddleware instances.
 * - string service names resolving to middleware
 * - arrays of service names and/or MiddlewareInterface instances
 * - PHP callable that follow the PSR-15 signature
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class RoutePipeline implements RequestHandlerInterface, MiddlewareInterface
{
    use Traits\MiddlewareTrait;

    /** @var null|ContainerInterface */
    protected $container;

    /** @var null|RequestHandlerInterface */
    private $handler;

    /**
     * @param null|ContainerInterface $container
     */
    public function __construct(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * Configures pipeline with target endpoint.
     *
     * @param RequestHandlerInterface $handler
     *
     * @return $this
     */
    public function withHandler(RequestHandlerInterface $handler): self
    {
        $pipeline          = clone $this;
        $pipeline->handler = $handler;

        return $pipeline;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $this->withHandler($handler)->handle($request);
    }

    /**
     * {@inheritdoc}
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (null === $handler = $this->handler) {
            throw new InvalidMiddlewareException('Unable to run pipeline, no handler given.');
        }

        if ($handler instanceof Router) {
            // Get the Default Router is available and ready for dispatching
            $handler = $this->getDefaultRouter($handler, $request);
        }

        return $this->pipeline()->process($request, $handler);
    }

    /**
     * Create a middleware pipeline from an array of middleware.
     *
     * Each item is passed to prepare() before being passed to the
     * MiddlewarePipe instance the method returns.
     *
     * @throws InvalidMiddlewareException if middleware has not one of
     *                                    the specified types
     *
     * @return MiddlewarePipe
     */
    public function pipeline(): MiddlewarePipe
    {
        $pipeline    = new MiddlewarePipe();
        $middlewares = $this->getMiddlewares();

        foreach ($middlewares as $middleware) {
            $pipeline->pipe($this->prepare($middleware));
        }

        return $pipeline;
    }

    /**
     * Gets the middlewares from stack
     *
     * @return array<int,MiddlewareInterface|string>
     */
    public function getMiddlewares(): array
    {
        return \array_values($this->middlewares);
    }

    /**
     * Return the default router
     *
     * @throws RouteNotFoundException
     *
     * @return RequestHandlerInterface
     */
    private function getDefaultRouter(Router $router, ServerRequestInterface &$request): RequestHandlerInterface
    {
        // Get the Route Handler ready for dispatching
        $handler = $router->match($request);

        /** @var RouteInterface $route */
        $route   = $request->getAttribute(Route::class);
        $request = $request->withAttribute(Route::class, $route);

        $this->addMiddleware(...$route->getMiddlewares());

        return $handler;
    }
}
