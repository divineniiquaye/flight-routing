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
use Flight\Routing\Exceptions\InvalidControllerException;
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

    /**
     * @return mixed
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, Route $route)
    {
        $route   = $request->getAttribute(Route::class, $route);
        $handler = $this->resolveResponse($request, $route);

        if ($handler instanceof ResponseInterface) {
            return $handler;
        }
        $arguments = [\get_class($request) => $request, \get_class($response) => $response];

        return $this->invoker->call($handler, \array_merge($arguments, $route->get('arguments')));
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
     * @return mixed
     */
    private function resolveResponse(ServerRequestInterface $request, Route $route)
    {
        $handler = $route->get('controller');

        if ($handler instanceof RequestHandlerInterface) {
            return $handler->handle($request);
        }

        if ($handler instanceof ResponseInterface) {
            return $handler;
        }

        if (null !== $this->namespace) {
            $handler = $this->resolveNamespace($handler);
        }

        // For a class that implements RequestHandlerInterface, we will call handle()
        // if no method has been specified explicitly
        if (\is_string($handler) && \is_a($handler, RequestHandlerInterface::class, true)) {
            return $this->invoker->call([$handler, 'handle'], [$request]);
        }

        // Disable or enable HTTP request method prefix for action.
        if (null !== $isRestFul = $route->get('defaults')['_api'] ?? null) {
            $method = \strtolower($request->getMethod());

            if (!\is_object($handler) || (\is_string($handler) && \class_exists($handler))) {
                throw new InvalidControllerException(
                    'Resource handler type should be a class string or class object, and not otherwise'
                );
            }

            return [$handler, $method . $isRestFul];
        }

        return $handler;
    }

    /**
     * @param callable|object|string|string[] $controller
     *
     * @return mixed
     */
    private function resolveNamespace($controller)
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
}
