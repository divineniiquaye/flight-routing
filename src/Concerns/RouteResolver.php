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

namespace Flight\Routing\Concerns;

use BiuradPHP\Routing\Route;
use Flight\Routing\RouteMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use BiuradPHP\Support\BoundMethod;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use BiuradPHP\Http\Interfaces\EmitterInterface;
use Flight\Routing\Interfaces\PublisherInterface;
use Flight\Routing\Exceptions\InvalidControllerException;
use Flight\Routing\Exceptions\InvalidMiddlewareException;
use BiuradPHP\DependencyInjection\Interfaces\FactoryInterface;
use Flight\Routing\Middlewares\RouteRequestHandler;

trait RouteResolver
{
    /** @var FactoryInterface */
    private $container;

    /** @var PublisherInterface */
    private $publisher;

    /** @var ServerRequestInterface */
    private $request;

    /** @var EmitterInterface */
    private $emitter;

    /** @var string|null */
    private $currentName;

    /** @var Route|null */
    private $currentRoute;

    /** @var string|null */
    private $currentDomain;

    /** @var string */
    private $currentPrefix;

    /** @var string */
    private $currentNamespace;

    /** @var array */
    private $currentMiddleware = [];

    /** @var array */
    public $routeMiddlewares = [];

    /**
     * Run the controller through the middleware (list).
     *
     * @param callable|RequestHandlerInterface|MiddlewareInterface $middlewares
     * @param ServerRequestInterface                               $request
     * @param RequestHandlerInterface                              $controllerRunner
     *
     * @return ResponseInterface|mixed|null
     *
     * @throws InvalidMiddlewareException
     * @throws ReflectionException
     */
    private function runControllerThroughMiddleware(array $middlewares, ServerRequestInterface &$request, $controllerRunner)
    {
        $pipe = new RouteMiddleware();
        $pipelines = array_map(function ($firewall) {
            // When the middleware is simply a Closure, we will return this Closure instance
            // directly so that Closures can be registered as middleware inline, which is
            // convenient on occasions when the developers are experimenting with them.
            // Same as object.
            if (is_object($firewall) || $firewall instanceof \Closure) {
                return $firewall;
            }

            if (is_string($firewall)) {
                if (array_key_exists($firewall, $this->routeMiddlewares)) {
                    $firewall = $this->getRouteMiddleware($firewall);
                }

                return is_null($this->container)
                    ? new $firewall() // Incase $container is set null. Let's create a new instance
                    : $this->container->get($firewall)
                ;
            }

            // Returning default.
            return $firewall;
        }, $middlewares);

        // This middleware is in the priority map. If we have encountered another middleware
        // that was also in the priority map and was at a lower priority than the current
        // middleware, we will move this middleware to be above the previous encounter.
        $middleware = $pipe->pipeline($pipelines);

        try {
            $requestHandler = $pipe->handler($controllerRunner);
        } finally {
            // This middleware is in the priority map; but, this is the first middleware we have
            // encountered from the map thus far. We'll save its current index plus its index
            // from the priority map so we can compare against them on the next iterations.
            return $middleware->process($request, $requestHandler);
        };
    }

    /**
     * Run the Middleware Controller.
     *
     * @param @param Closure|callable|string $middleware
     * @param array                          $parameters
     */
    private function resolveController($middleware, array $parameters)
    {
        $className = $this->parseController($middleware);

        if (! $className[0] instanceof RequestHandlerInterface) {
            $middleware = new RouteRequestHandler($this, $middleware, $parameters);
        } else {
            $middleware = $className[0];
        }

        try {
            return $middleware;
        } catch (\Throwable $e) {
            throw new InvalidMiddlewareException($e->getMessage(), $e->getCode(), $e->getPrevious());
        }
    }

    /**
     * Parses class::method, class@method and callables.
     *
     * @param @param Closure|callable|string $middleware
     */
    private function parseController($middleware)
    {
        // Let's call it to avoid errors.
        $className = null;
        $methodName = null;

        if (is_string($middleware)) {
            if (mb_strpos($middleware, '@')) {
                [$className, $methodName] = explode('@', $middleware);
            } elseif (mb_strpos($middleware, '::')) {
                [$className, $methodName] = explode('::', $middleware);
            }
        } elseif (is_array($middleware)) {
            [$className, $methodName] = $middleware;
        }

        if (! is_null($className) && ! is_null($methodName)) {
            if (null !== $this->container) {
                return [$this->container->get($className), $methodName];
            }

            return [new $className(), $methodName];
        }

        return [$middleware];
    }

    /**
     * Run the controller.
     *
     * @param Closure|callable|string                $controller
     * @param array                                  $parameters
     * @param Psr\Http\Message\ServerRequestInterface $request
     *
     * @return ResponseInterface|null
     *
     * @throws InvalidControllerException
     */
    public function runController($controller, array $parameters, ServerRequestInterface &$request = null)
    {
        if (! $controller instanceof \Closure) {
            $controller = $this->parseController($controller);
        }

        return BoundMethod::call($this->container, $controller, $parameters);

        throw new InvalidControllerException('Invalid controller for route: ' . $this->currentRoute);
    }

    /**
     * Get current http request instance.
     *
     * @return ServerRequestInterface
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Set my own http request instance.
     *
     * @param ServerRequestInterface $request
     */
    public function setRequest(ServerRequestInterface $request)
    {
        $this->request = $request;

        return $this;
    }

    /**
     * Get current http response instance.
     *
     * @return EmitterInterface|null
     */
    public function getEmitter()
    {
        return $this->emitter;
    }

    /**
     * Set the response instance with emitter.
     *
     * @param EmitterInterface|null $emitter
     */
    public function setEmitter(?EmitterInterface $emitter = null)
    {
        $this->emitter = $emitter;

        return $this;
    }

    /**
     * @return PublisherInterface
     */
    public function getPublisher()
    {
        return $this->publisher;
    }

    /**
     * @param PublisherInterface $publisher
     */
    public function setPublisher(PublisherInterface $publisher)
    {
        $this->publisher = $publisher;
    }

    /**
     * Get the value of currentNamespace
     *
     * @return  string
     */
    public function getCurrentNamespace()
    {
        return $this->currentNamespace;
    }

    /**
     * Get the value of currentPrefix
     *
     * @return  string
     */
    public function getCurrentPrefix()
    {
        return $this->currentPrefix;
    }

    /**
     * Get the value of currentName
     *
     * @return  string|null
     */
    public function getCurrentName()
    {
        return $this->currentName;
    }

    /**
     * Get the value of currentDomain
     *
     * @return  string|null
     */
    public function getCurrentDomain()
    {
        return $this->currentDomain;
    }

    /**
     * Get the value of currentMiddleware
     *
     * @return  array
     */
    public function getCurrentMiddleware()
    {
        return $this->currentMiddleware;
    }
}
