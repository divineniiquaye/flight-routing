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

namespace Flight\Routing;

use DivineNii\Invoker\Interfaces\InvokerInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RouteResolver
{
    /** @var InvokerInterface */
    private $invoker;

    /** @var string */
    private $namespace;

    public function __construct(InvokerInterface $invoker)
    {
        $this->invoker = $invoker;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, Route $route)
    {
        $route   = $request->getAttribute(Route::class, $route);
        $handler = $this->resolveRestFul($request, $route);

        if (null !== $this->namespace) {
            $handler = $this->resolveNamespace($handler);
        }

        // For a class that implements RequestHandlerInterface, we will call handle()
        // if no method has been specified explicitly
        if (\is_string($handler) && \is_a($handler, RequestHandlerInterface::class, true)) {
            $handler = [$handler, 'handle'];
        }

        return $this->invoker->call(
            $route->getController(),
            \array_merge(
                [\get_class($request) => $request, \get_class($response) => $response],
                $route->getArguments()
            )
        );
    }

    /**
     * Set Namespace for route handlers/controllers
     *
     * @param string $namespace
     */
    public function setNamespace(string $namespace): void
    {
        $this->namespace = \rtrim($namespace, '\\/') . '\\';
    }

    /**
     * Gets the PSR-11 container instance
     *
     * @return null|ContainerInterface
     */
    public function getContainer(): ?ContainerInterface
    {
        return $this->invoker->getContainer();
    }

    /**
     * @param callable|object|string|string[] $controller
     *
     * @return mixed
     */
    protected function resolveNamespace($controller)
    {
        if (
            (\is_string($controller) && !\class_exists($controller)) &&
            !str_starts_with($controller, $this->namespace)
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
     * @param ServerRequestInterface $request
     * @param string                 $name
     */
    private function getResourceMethod(ServerRequestInterface $request, string $name): string
    {
        return \strtolower($request->getMethod()) . \ucfirst($name);
    }
}
