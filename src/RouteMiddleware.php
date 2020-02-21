<?php

declare(strict_types=1);

/*
 * This code is under BSD 3-Clause "New" or "Revised" License.
 *
 * PHP version 7 and above required
 *
 * @category  RoutingManager
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 Biurad Group (https://biurad.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * @link      https://www.biurad.com/projects/routingmanager
 * @since     Version 0.1
 */

namespace Flight\Routing;

use function count;
use function is_array;
use function is_string;
use function array_shift;
use function is_callable;

use Laminas\Stratigility\MiddlewarePipe;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use BiuradPHP\Http\Exceptions\InvalidMiddlewareException;
use Laminas\Stratigility\Middleware\RequestHandlerMiddleware;
use Laminas\Stratigility\Middleware\CallableMiddlewareDecorator;

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
 * - PHP callables that follow the PSR-15 signature
 *
 * Additionally, the class provides the following decorator/utility methods:
 *
 * - callable() will decorate the callable middleware passed to it using
 *   CallableMiddlewareDecorator.
 * - handler() will decorate the request handler passed to it using
 *   RequestHandlerMiddleware.
 * - pipeline() will create a MiddlewarePipe instance from the array of
 *   middleware passed to it, after passing each first to prepare().
 */
class RouteMiddleware
{
    /**
     * @param string|array|callable|MiddlewareInterface|RequestHandlerInterface $middleware
     *
     * @return MiddlewareInterface
     * @throws InvalidMiddlewareException if argument is not one of
     *                                              the specified types
     */
    public function prepare($middleware): MiddlewareInterface
    {
        if ($middleware instanceof MiddlewareInterface) {
            return $middleware;
        }

        if ($middleware instanceof RequestHandlerInterface) {
            return $this->handler($middleware);
        }

        if (is_callable($middleware)) {
            return $this->callable($middleware);
        }

        if (is_array($middleware)) {
            return $this->pipeline(...$middleware);
        }

        if (!is_string($middleware) || $middleware === '') {
            throw InvalidMiddlewareException::forMiddleware($middleware);
        }
    }

    /**
     * Decorate callable standards-signature middleware via a CallableMiddlewareDecorator.
     * @param callable $middleware
     * @return CallableMiddlewareDecorator
     */
    public function callable(callable $middleware): CallableMiddlewareDecorator
    {
        return new CallableMiddlewareDecorator($middleware);
    }

    /**
     * Decorate a RequestHandlerInterface as middleware via RequestHandlerMiddleware.
     * @param RequestHandlerInterface $handler
     * @return RequestHandlerMiddleware
     */
    public function handler(RequestHandlerInterface $handler): RequestHandlerMiddleware
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
     * @param string|array|MiddlewarePipe $middleware
     * @return MiddlewarePipe
     */
    public function pipeline(...$middleware): MiddlewarePipe
    {
        // Allow passing arrays of middleware or individual lists of middleware
        if (is_array($middleware[0])
            && count($middleware) === 1
        ) {
            $middleware = array_shift($middleware);
        }

        $pipeline = new MiddlewarePipe();
        foreach ($middleware as $m) {
            $pipeline->pipe($this->prepare($m));
        }

        return $pipeline;
    }
}
