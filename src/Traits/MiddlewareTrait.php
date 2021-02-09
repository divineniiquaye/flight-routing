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

namespace Flight\Routing\Traits;

use Flight\Routing\Exceptions\InvalidMiddlewareException;
use Flight\Routing\Route;
use Laminas\Stratigility\Middleware\CallableMiddlewareDecorator;
use Laminas\Stratigility\Middleware\RequestHandlerMiddleware;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Provides ability to manage set of middleware.
 */
trait MiddlewareTrait
{
    /**
     * Set of route middleware to be used in $middlewares
     * Stack, if string name is equal to a given middleware.
     *
     * @var array<string,mixed>
     */
    protected $nameMiddlewares = [];

    /**
     * Add new middleware(s) at the end of chain.
     *
     * Example (in bootstrap):
     * $this->addMiddleware(new ProxyMiddleware());
     *
     * @param array<string,mixed>|callable|MiddlewareInterface|RequestHandlerInterface|string ...$middlewares
     */
    public function addMiddleware(...$middlewares): void
    {
        foreach ($middlewares as $middleware) {
            if (\is_array($middleware) && !\is_callable($middleware)) {
                $this->addRecursiveMiddleware($middleware);

                continue;
            }

            $this->pipe($middleware);
        }
    }

    /**
     * @param array<int|string,mixed> $middlewares
     */
    protected function addRecursiveMiddleware(array $middlewares): void
    {
        foreach ($middlewares as $index => $middleware) {
            if (\is_string($index)) {
                $this->nameMiddlewares[$index] = $middleware;

                continue;
            }

            $this->addMiddleware($middleware);
        }
    }

    /**
     * Add a new middleware to the stack.
     *
     * Middleware are organized as a stack. That means middleware
     * that have been added before will be executed after the newly
     * added one (last in, first out).
     *
     * @param mixed $middleware
     *
     * @throws InvalidMiddlewareException if argument is not one of
     *                                    the specified types
     *
     * @return MiddlewareInterface
     */
    private function resolveMiddleware($middleware): MiddlewareInterface
    {
        if (\is_string($middleware) && null !== $container = $this->resolver->getContainer()) {
            try {
                $middleware = $container->get($middleware);
            } catch (NotFoundExceptionInterface $e) {
                // ... handled at the end
            }
        }

        if (\is_string($middleware) && \class_exists($middleware)) {
            $middleware = new $middleware();
        }

        if ($middleware instanceof RequestHandlerInterface) {
            return new RequestHandlerMiddleware($middleware);
        }

        if (\is_callable($middleware)) {
            return new CallableMiddlewareDecorator($middleware);
        }

        if (!$middleware instanceof MiddlewareInterface) {
            throw InvalidMiddlewareException::forMiddleware($middleware);
        }

        return $middleware;
    }

    /**
     * @return MiddlewareInterface[]
     */
    private function resolveMiddlewares(Route $route): array
    {
        $middlewares = [];

        foreach ($route->get('middlewares') as $middleware) {
            if (\is_string($middleware) && isset($this->nameMiddlewares[$middleware])) {
                $middlewares[] = $this->resolveMiddleware($this->nameMiddlewares[$middleware]);

                continue;
            }

            $middlewares[] = $this->resolveMiddleware($middleware);
        }

        return $middlewares;
    }
}
