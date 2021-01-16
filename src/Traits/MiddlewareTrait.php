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
     * @var array<string,mixed>
     */
    protected $middlewares = [];

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

            $hash = $this->getMiddlewareHash($middleware);

            if (isset($this->middlewares[$hash])) {
                throw new DuplicateRouteException(\sprintf('A middleware with the hash "%s" already exists.', $hash));
            }

            $this->middlewares[$hash] = $middleware;
        }
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
     * @param mixed $middleware
     *
     * @return string
     */
    private function getMiddlewareHash($middleware): string
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
