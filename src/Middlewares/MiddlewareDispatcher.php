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

namespace Flight\Routing\Middlewares;

use Flight\Routing\Exceptions\InvalidMiddlewareException;
use Laminas\Stratigility\Middleware\CallableMiddlewareDecorator;
use Laminas\Stratigility\Middleware\RequestHandlerMiddleware;
use Laminas\Stratigility\MiddlewarePipe;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Marshal middleware for use in the application.
 *
 * This class provides a number of methods for preparing and returning
 * middleware for use within an application.
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
 * Additionally, the class provides the following decorator/utility methods:
 *
 * - addCallable() will decorate the callable middleware passed to it using
 *   CallableMiddlewareDecorator.
 * - addHandler() will decorate the request handler passed to it using
 *   RequestHandlerMiddleware.
 * - pipeline() will create a MiddlewarePipe instance from the array of
 *   middleware passed to it, after passing each first to prepare().
 */
class MiddlewareDispatcher
{
    /**
     * Set of middleware to be applied for every request.
     *
     * @var MiddlewareInterface[]|string[]
     */
    protected $middlewares = [];

    /**
     * Set of route middleware to be used in $middlewares
     * Stack, if string name is equal to a given middleware.
     *
     * @var array<string,mixed>
     */
    protected $routeMiddlewares = [];

    /**
     * @var null|ContainerInterface
     */
    protected $container;

    /**
     * @param array                   $routeMiddlewares
     * @param null|ContainerInterface $container
     */
    public function __construct(array $routeMiddlewares, ContainerInterface $container = null)
    {
        $this->routeMiddlewares = $routeMiddlewares;
        $this->container        = $container;
    }

    /**
     * Add new middleware to the the stack.
     *
     * NOTE: This method adds middleware at the top of chain,
     * if it's an array else pushes middleware to the end of chain.
     *
     * Middleware are organized as a stack. That means middleware
     * that have been added before will be executed after the newly
     * added one (last in, first out).
     *
     * Example (in implementation):
     * $this->add(new ProxyMiddleware());
     *
     * or
     *
     * $this->add([ProxyMiddleware::class]);
     *
     * @param mixed $middleware
     */
    public function add($middleware): void
    {
        if (\is_array($middleware)) {
            $this->routeMiddlewares = \array_merge($middleware['routing'] ?? [], $this->routeMiddlewares);
            unset($middleware['routing']);

            $this->middlewares = \array_merge($middleware, $this->middlewares);

            return;
        }

        if (null !== $middleware) {
            $this->middlewares[] = $middleware;
        }
    }

    /**
     * Get all middlewares stack.
     *
     * @return MiddlewareInterface[]|string[]
     */
    public function getMiddlewareStack(): array
    {
        return \array_filter($this->middlewares);
    }

    /**
     * Resolve a middleware so it can be used flexibly.
     *
     * @param callable|MiddlewareInterface|string $middleware
     *
     * @return callable|MiddlewareInterface|RequestHandlerInterface
     */
    public function resolve($middleware)
    {
        if (\is_string($middleware)) {
            if (\array_key_exists($middleware, $this->routeMiddlewares)) {
                $middleware = $this->routeMiddlewares[$middleware];
            }

            if (
                (null !== $this->container && \is_string($middleware)) &&
                $this->container->has($middleware)
            ) {
                $middleware = $this->container->get($middleware);
            }

            if (\is_string($middleware) && !\class_exists($middleware)) {
                throw InvalidMiddlewareException::forMiddleware($middleware);
            }

            return \is_object($middleware) ? $middleware : new $middleware();
        }

        return $middleware;
    }

    /**
     * Add a new middleware to the stack.
     *
     * Middleware are organized as a stack. That means middleware
     * that have been added before will be executed after the newly
     * added one (last in, first out).
     *
     * @param callable|MiddlewareInterface|RequestHandlerInterface|string|string[] $middleware
     *
     * @throws InvalidMiddlewareException if argument is not one of
     *                                    the specified types
     *
     * @return MiddlewareInterface
     */
    public function prepare($middleware): MiddlewareInterface
    {
        if ($middleware instanceof MiddlewareInterface) {
            return $middleware;
        }

        if ($middleware instanceof RequestHandlerInterface) {
            return $this->addHandler($middleware);
        }

        if (\is_callable($middleware)) {
            return $this->addCallable($middleware);
        }

        if (\is_array($middleware)) {
            return $this->pipeline(...$middleware);
        }

        throw InvalidMiddlewareException::forMiddleware($middleware);
    }

    /**
     * Decorate callable standards-signature middleware via a CallableMiddlewareDecorator.
     *
     * @param callable $middleware
     *
     * @return CallableMiddlewareDecorator
     */
    public function addCallable(callable $middleware): CallableMiddlewareDecorator
    {
        return new CallableMiddlewareDecorator($middleware);
    }

    /**
     * Decorate a RequestHandlerInterface as middleware via RequestHandlerMiddleware.
     *
     * @param RequestHandlerInterface $handler
     *
     * @return RequestHandlerMiddleware
     */
    public function addHandler(RequestHandlerInterface $handler): RequestHandlerMiddleware
    {
        return new RequestHandlerMiddleware($handler);
    }

    /**
     * Create a middleware pipeline from an array of middleware.
     *
     * This method allows passing an array of middleware as either:
     *
     * - discrete arguments
     * - an array of middleware, using the splat operator: pipeline(...$array)
     * - an array of middleware as the sole argument: pipeline($array)
     *
     * Each item is passed to prepare() before being passed to the
     * MiddlewarePipe instance the method returns.
     *
     * @param MiddlewareInterface[]|string|string[]|callable $middleware
     *
     * @return MiddlewarePipe
     */
    public function pipeline(...$middleware): MiddlewarePipe
    {
        // Allow passing arrays of middleware or individual lists of middleware
        if (\is_array($middleware[0]) && \count($middleware) === 1) {
            $middleware = \array_shift($middleware);
        }

        $pipeline = new MiddlewarePipe();

        foreach (\array_map([$this, 'resolve'], (array) $middleware) as $m) {
            $pipeline->pipe($this->prepare($m));
        }

        return $pipeline;
    }
}
