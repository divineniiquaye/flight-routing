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

use Laminas\Stratigility\MiddlewarePipe;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;
use BiuradPHP\Http\Exceptions\InvalidMiddlewareException;
use Laminas\Stratigility\Middleware\RequestHandlerMiddleware;
use Laminas\Stratigility\Middleware\CallableMiddlewareDecorator;

use function count;
use function is_array;
use function is_string;
use function array_shift;
use function is_callable;
use function array_merge;
use function array_filter;
use function strpos;
use function explode;
use function is_subclass_of;
use function method_exists;
use function array_values;
use function sprintf;

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
 * - addCallable() will decorate the callable middleware passed to it using
 *   CallableMiddlewareDecorator.
 * - addHandler() will decorate the request handler passed to it using
 *   RequestHandlerMiddleware.
 * - pipeline() will create a MiddlewarePipe instance from the array of
 *   middleware passed to it, after passing each first to prepare().
 */
class RouteMiddleware
{
    /**
     * Set of middleware to be applied for every request.
     *
     * @var MiddlewareInterface[]
     */
    protected $middlewares = [];

    /**
     * Set of route middleware to be used in $middlewares
     * Stack, if string name is equal to a given middleware.
     *
     * @var array<string: MiddlewareInterface>
     */
    protected $routeMiddlewares = [];

    /**
     * @var ContainerInterface|null
     */
    protected $container;

    /**
     * @param array $routeMiddlewares
     * @param ContainerInterface|null $container
     */
    public function __construct(array $routeMiddlewares, ContainerInterface $container = null) {
        $this->routeMiddlewares = $routeMiddlewares;
        $this->container = $container;
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
     * @param MiddlewareInterface|string|array|callable $middleware
     */
    public function add($middleware): void
    {
        if (is_array($middleware)) {
            if (isset($middleware['routing'])) {
                $this->routeMiddlewares = array_merge($middleware['routing'], $this->routeMiddlewares);
                unset($middleware['routing']);
            }

            $this->middlewares = array_merge($middleware, $this->middlewares);
            return;
        }

        if (null !== $middleware) {
            $this->middlewares[] = $middleware;
        }
    }

    /**
     * Get all middlewares stack
     *
     * @return array<string, MiddlewareInterface>
     */
    public function getMiddlewareStack(): array
    {
        return array_filter($this->middlewares);
    }

    /**
     * Resolve a middleware so it can be used flexibly.
     *
     * @param MiddlewareInterface|string|callable $middleware
     * @return MiddlewareInterface
     */
    public function resolve($middleware): MiddlewareInterface
    {
        if ($middleware instanceof MiddlewareInterface) {
            return $middleware;
        }

        if (is_string($middleware)) {
            $arguments = [];
            if (false !== strpos($middleware, ':')) {
                [$middleware, $args] = explode(':', $middleware);
                $arguments = false !== strpos($args, ',') ? explode(',', $args) : [$args];
            }

            if (array_key_exists($middleware, $this->routeMiddlewares)) {
                $middleware = $this->routeMiddlewares[$middleware];
            }

            $middleware = !$this->container instanceof ContainerInterface
                ? new $middleware() // Incase $container is set null. Let's create a new instance
                : $this->container->get($middleware)
            ;

            if ($middleware instanceof RequestHandlerInterface) {
                $middleware = $this->addHandler($middleware);
            }

            if (is_subclass_of($middleware, MiddlewareInterface::class)) {
                // Allowing parameters to be passed to middleware
                if (method_exists($middleware, 'setOptions')) {
                    $middleware->setOptions(...array_values($arguments));
                }

                return $middleware;
            }

            throw new InvalidMiddlewareException(sprintf('%s is not resolvable', $middleware));
        }

        if ($middleware instanceof RequestHandlerInterface) {
            return $this->addHandler($middleware);
        }

        if (is_callable($middleware)) {
            return $this->addCallable($middleware);
        }
    }

    /**
     * Add a new middleware to the stack
     *
     * Middleware are organized as a stack. That means middleware
     * that have been added before will be executed after the newly
     * added one (last in, first out).
     *
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
            return $this->addHandler($middleware);
        }

        if (is_callable($middleware)) {
            return $this->addCallable($middleware);
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
    public function addCallable(callable $middleware): CallableMiddlewareDecorator
    {
        return new CallableMiddlewareDecorator($middleware);
    }

    /**
     * Decorate a RequestHandlerInterface as middleware via RequestHandlerMiddleware.
     * @param RequestHandlerInterface $handler
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
     * @param string|array|MiddlewarePipe $middleware
     * @return MiddlewarePipe
     */
    public function pipeline(...$middleware): MiddlewarePipe
    {
        // Allow passing arrays of middleware or individual lists of middleware
        if (is_array($middleware[0]) && count($middleware) === 1) {
            $middleware = array_shift($middleware);
        }

        $pipeline = new MiddlewarePipe();
        foreach (array_map([$this, 'resolve'], $middleware) as $m) {
            $pipeline->pipe($this->prepare($m));
        }

        return $pipeline;
    }
}
