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
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class MiddlewareDispatcher
{
    /** @var null|ContainerInterface */
    private $container;

    public function __construct(?ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param array<int,MiddlewareInterface|string> $middlewares
     */
    public function dispatch(
        array $middlewares,
        RequestHandlerInterface $handler,
        ServerRequestInterface $request
    ): ResponseInterface {
        if ([] === $middlewares) {
            return $handler->handle($request);
        }

        $pipeline = new MiddlewarePipe();

        foreach ($middlewares as $middleware) {
            $pipeline->pipe($this->prepare($middleware));
        }

        return $pipeline->process($request, $handler);
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
    private function prepare($middleware): MiddlewareInterface
    {
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
}
