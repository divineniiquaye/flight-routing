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

use Closure;
use Flight\Routing\Exceptions\DuplicateRouteException;
use Flight\Routing\Exceptions\InvalidMiddlewareException;
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
     * Set of middleware to be applied for every request.
     *
     * @var array<int|string,mixed>
     */
    protected $middlewares = [];

    /**
     * Set of route middleware to be used in $middlewares
     * Stack, if string name is equal to a given middleware.
     *
     * @var array<int|string,mixed>
     */
    protected $nameMiddlewares = [];

    /**
     * Add new middleware(s) at the end of chain.
     *
     * Example (in bootstrap):
     * $this->addMiddleware(new ProxyMiddleware());
     *
     * @param array<string,mixed>|callable|MiddlewareInterface|RequestHandlerInterface|string ...$middlewares
     *
     * @throws DuplicateRouteException
     */
    public function addMiddleware(...$middlewares): void
    {
        foreach ($middlewares as $middleware) {
            if (\is_array($middleware) && !\is_callable($middleware)) {
                $this->addRecursiveMiddleware($middleware);

                continue;
            }

            $hash = $this->getHash($middleware);

            if (isset($this->middlewares[$hash])) {
                throw new DuplicateRouteException(\sprintf('A middleware with the hash "%s" already exists.', $hash));
            }

            $this->middlewares[$hash] = $middleware;
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
    protected function prepare($middleware): MiddlewareInterface
    {
        if (\is_string($middleware) && \array_key_exists($middleware, $this->nameMiddlewares)) {
            $middleware = $this->nameMiddlewares[$middleware];
        }

        if (\is_string($middleware) && null !== $this->container) {
            try {
                $middleware = $this->container->get($middleware);
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
     * @param mixed $middleware
     *
     * @return string
     */
    private function getHash($middleware): string
    {
        if ($middleware instanceof Closure || \is_object($middleware)) {
            return \spl_object_hash($middleware);
        }

        if (\is_callable($middleware) && \count($middleware) === 2) {
            return $middleware[1];
        }

        return \md5($middleware);
    }
}
