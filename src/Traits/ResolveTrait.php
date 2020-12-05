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
use DivineNii\Invoker\Exceptions\NotCallableException;
use DivineNii\Invoker\Interfaces\InvokerInterface;
use Flight\Routing\Interfaces\RouteInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

trait ResolveTrait
{
    /** @var null|RouteInterface */
    private $route;

    /** @var InvokerInterface */
    private $resolver;

    /**
     * @param ServerRequestInterface $request
     * @param string                 $path
     *
     * @return string
     */
    protected function resolvePath(ServerRequestInterface $request, string $path): string
    {
        $requestPath = $path;

        if ('cli' !== \PHP_SAPI) {
            $basePath    = \dirname($request->getServerParams()['SCRIPT_NAME'] ?? '');
            $requestPath = \substr($requestPath, \strlen($basePath)) ?: '/';
        }

        if (\strlen($requestPath) > 1) {
            $requestPath = \rtrim($requestPath, '/');
        }

        return \rawurldecode($requestPath);
    }

    /**
     * @param callable|object|string|string[] $controller
     *
     * @return mixed
     */
    protected function resolveNamespace($controller)
    {
        if ($controller instanceof Closure || null === $namespace = $this->namespace) {
            return $controller;
        }

        if ((\is_string($controller) && !\class_exists($controller)) &&
            !str_starts_with($controller, $namespace)
        ) {
            $controller = \is_callable($controller) ? $controller : $this->namespace . \ltrim($controller, '\\/');
        }

        if (\is_array($controller) && (!\is_object($controller[0]) && !\class_exists($controller[0]))) {
            $controller[0] = $this->namespace . \ltrim($controller[0], '\\/');
        }

        return $controller;
    }

    /**
     * @param ServerRequestInterface $request
     * @param RouteInterface         $route
     *
     * @throws NotCallableException
     *
     * @return callable|ResponseInterface
     */
    protected function resolveController(ServerRequestInterface $request, RouteInterface $route)
    {
        $handler = $this->resolveNamespace($this->resolveRestFul($request, $route));

        // For a class that implements RequestHandlerInterface, we will call handle()
        // if no method has been specified explicitly
        if (\is_string($handler) && \is_a($handler, RequestHandlerInterface::class, true)) {
            $handler = [$handler, 'handle'];
        }

        // If controller is instance of RequestHandlerInterface
        if ($handler instanceof RequestHandlerInterface) {
            return $handler->handle($request);
        }

        return $this->resolver->getCallableResolver()->resolve($handler);
    }

    /**
     * @param ServerRequestInterface $request
     * @param RouteInterface         $route
     *
     * @return mixed
     */
    protected function resolveRestFul(ServerRequestInterface $request, RouteInterface $route)
    {
        $controller = $route->getController();

        // Disable or enable HTTP request method prefix for action.
        if (str_ends_with($route->getName(), '__restful')) {
            switch (true) {
                case \is_array($controller):
                    $controller[1] = $this->getResourceMethod($request, $controller[1]);

                    break;

                case \is_string($controller) && \class_exists($controller):
                    $controller = [
                        $controller,
                        $this->getResourceMethod($request, \substr($route->getName(), -0, -9)),
                    ];

                    break;
            }
        }

        return $controller;
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     *
     * @return mixed
     */
    protected function resolveRoute(ServerRequestInterface $request, ResponseInterface $response)
    {
        $handler = $this->route->getController();

        if ($handler instanceof ResponseInterface) {
            return $handler;
        }

        $arguments = \array_merge(
            $this->route->getArguments(),
            [\get_class($request) => $request, \get_class($response) => $response]
        );

        return $this->resolver->call($handler, $arguments);
    }

    /**
     * @param ServerRequestInterface $request
     * @param RouteInterface         $route
     */
    protected function resolveListeners(ServerRequestInterface $request, RouteInterface $route): void
    {
        $route->setController($this->resolveController($request, $route));
        $this->addMiddleware(...$route->getMiddlewares());

        foreach ($this->listeners as $listener) {
            $listener->onRoute($request, $route);
        }

        $this->route = clone $route;
    }
}
