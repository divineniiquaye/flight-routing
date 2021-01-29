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

use DivineNii\Invoker\Exceptions\NotCallableException;
use DivineNii\Invoker\Interfaces\InvokerInterface;
use Flight\Routing\Handlers\RouteHandler;
use Flight\Routing\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

trait ResolveTrait
{
    use MiddlewareTrait;

    /** @var null|string */
    private $namespace;

    /** @var null|Route */
    private $route;

    /** @var InvokerInterface */
    private $resolver;

    /**
     * @param callable|object|string|string[] $controller
     *
     * @return mixed
     */
    protected function resolveNamespace($controller)
    {
        if (\is_callable($controller) || null === $namespace = $this->namespace) {
            return $controller;
        }

        if (
            (\is_string($controller) && !\class_exists($controller)) &&
            !str_starts_with($controller, $namespace)
        ) {
            $controller = $this->namespace . \ltrim($controller, '\\/');
        }

        if (\is_array($controller) && (!\is_object($controller[0]) && !\class_exists($controller[0]))) {
            $controller[0] = $this->namespace . \ltrim($controller[0], '\\/');
        }

        return $controller;
    }

    /**
     * @param ServerRequestInterface $request
     * @param Route                  $route
     *
     * @return mixed
     */
    protected function resolveRestFul(ServerRequestInterface $request, Route $route)
    {
        $controller = $route->getController();
        $routeName  = (string) $route->getName();

        // Disable or enable HTTP request method prefix for action.
        if (str_ends_with($routeName, '__restful')) {
            switch (true) {
                case \is_array($controller):
                    $controller[1] = $this->getResourceMethod($request, $controller[1]);

                    break;

                case \is_string($controller) && \class_exists($controller):
                    $controller = [
                        $controller,
                        $this->getResourceMethod($request, \substr($routeName, -0, -9)),
                    ];

                    break;
            }
        }

        return $controller;
    }

    /**
     * @param Route $route
     *
     * @throws NotCallableException
     *
     * @return RequestHandlerInterface
     */
    protected function resolveHandler(Route $route): RequestHandlerInterface
    {
        $handler = $route->getController();

        if (!$handler instanceof RequestHandlerInterface) {
            $handler = new RouteHandler(
                function (ServerRequestInterface $request, ResponseInterface $response) use ($route) {
                    $handler  = $this->resolveNamespace($this->resolveRestFul($request, $route));
                    $resolver = $this->resolver;

                    // For a class that implements RequestHandlerInterface, we will call handle()
                    // if no method has been specified explicitly
                    if (\is_string($handler) && \is_a($handler, RequestHandlerInterface::class, true)) {
                        $handler = [$handler, 'handle'];
                    }

                    $route->run($resolver->getCallableResolver()->resolve($handler));

                    foreach ($this->listeners as $listener) {
                        $listener->onRoute($request, $route);
                    }

                    $requestResponse = [\get_class($request) => $request, \get_class($response) => $response];

                    return $resolver->call(
                        $route->getController(),
                        \array_merge($requestResponse, $route->getDefaults()['_arguments'] ?? [])
                    );
                },
                $this->responseFactory
            );
        }

        // If controller is instance of RequestHandlerInterface
        return $handler;
    }

    /**
     * @param Route                        $route
     * @param array<string,string>         $parameters
     * @param array<int|string,int|string> $queryParams
     *
     * @return string
     */
    protected function resolveUri(Route $route, array $parameters, array $queryParams): string
    {
        $prefix  = '.'; // Append missing "." at the beginning of the $uri.

        // Making routing on sub-folders easier
        if (\strpos($createdUri = $this->getMatcher()->buildPath($route, $parameters), '/') !== 0) {
            $prefix .= '/';
        }

        // Incase query is added to uri.
        if (!empty($queryParams)) {
            $createdUri .= '?' . \http_build_query($queryParams);
        }

        if (\strpos($createdUri, '://') === false) {
            $createdUri = $prefix . $createdUri;
        }

        return \rtrim($createdUri, '/');
    }

    /**
     * @param Route $route
     *
     * @return array<int,mixed>
     */
    protected function resolveMiddlewares(Route $route): array
    {
        $middlewares = [];

        foreach ($route->getMiddlewares() as $middleware) {
            if (\is_string($middleware) && isset($this->nameMiddlewares[$middleware])) {
                $middlewares[] = $this->nameMiddlewares[$middleware];

                continue;
            }

            $middlewares[] = $middleware;
        }

        return $middlewares;
    }

    /**
     * @param ServerRequestInterface $request
     * @param string                 $name
     */
    private function getResourceMethod(ServerRequestInterface $request, string $name): string
    {
        return \strtolower($request->getMethod()) . \ucfirst($name);
    }
}
