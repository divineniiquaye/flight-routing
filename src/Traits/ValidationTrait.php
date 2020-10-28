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
use Flight\Routing\Interfaces\RouteInterface;
use Flight\Routing\Router;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

trait ValidationTrait
{
    /**
     * @param callable|object|string|string[] $controller
     *
     * @return mixed
     */
    protected function resolveNamespace($controller)
    {
        $namespace = $this->namespace;

        if (null !== $namespace && (\is_string($controller) || !$controller instanceof Closure)) {
            if (
                (
                    \is_string($controller) &&
                    !\class_exists($controller)
                ) &&
                !str_starts_with($controller, $namespace)
            ) {
                $controller = \is_callable($controller) ? $controller : $this->namespace . \ltrim($controller, '\\/');
            }

            if (\is_array($controller) && (!\is_object($controller[0]) && !\class_exists($controller[0]))) {
                $controller[0] = $this->namespace . \ltrim($controller[0], '\\/');
            }
        }

        return $controller;
    }

    /**
     * @param ServerRequestInterface $request
     * @param RouteInterface         $route
     *
     * @return mixed
     */
    protected function resolveController(ServerRequestInterface $request, RouteInterface &$route)
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

        $handler = $this->resolveNamespace($controller);

        // For a class that implements RequestHandlerInterface, we will call handle()
        // if no method has been specified explicitly
        if (\is_string($handler) && \is_a($handler, RequestHandlerInterface::class, true)) {
            $handler = [$handler, 'handle'];
        }

        // If controller is instance of RequestHandlerInterface
        if ($handler instanceof RequestHandlerInterface) {
            return $handler->handle($request);
        }

        return $handler;
    }

    /**
     * Check if given request method matches given route method.
     *
     * @param string[] $routeMethod
     * @param string   $requestMethod
     *
     * @return bool
     */
    private function compareMethod(array $routeMethod, string $requestMethod): bool
    {
        return \in_array($requestMethod, $routeMethod, true);
    }

    /**
     * Check if given request domain matches given route domain.
     *
     * @param null|string              $routeDomain
     * @param string                   $requestDomain
     * @param array<int|string,string> $parameters
     *
     * @return bool
     */
    private function compareDomain(?string $routeDomain, string $requestDomain, array &$parameters): bool
    {
        return ($routeDomain === null || empty($routeDomain)) ||
            (bool) \preg_match($routeDomain, $requestDomain, $parameters);
    }

    /**
     * Check if given request uri matches given uri method.
     *
     * @param string                   $routeUri
     * @param string                   $requestUri
     * @param array<int|string,string> $parameters
     *
     * @return bool
     */
    private function compareUri(string $routeUri, string $requestUri, array &$parameters): bool
    {
        return (bool) \preg_match($routeUri, $requestUri, $parameters);
    }

    /**
     * Check if given request uri scheme matches given route scheme.
     *
     * @param string[] $routeScheme
     * @param string   $requestScheme
     *
     * @return bool
     */
    private function compareScheme(array $routeScheme, string $requestScheme): bool
    {
        return empty($routeScheme) || \in_array($requestScheme, $routeScheme, true);
    }

    /**
     * Get merged default parameters.
     *
     * @param array<int|string,mixed> $params
     * @param array<string,string>    $defaults
     *
     * @return array<string,string> Merged default parameters
     */
    private function mergeDefaults(array $params, array $defaults): array
    {
        foreach ($params as $key => $value) {
            if (!\is_int($key) && (!isset($defaults[$key]) || null !== $value)) {
                $defaults[$key] = $value;
            }
        }

        return $defaults;
    }

    /**
     * Merge Router attributes in route default and patterns.
     *
     * @param RouteInterface $route
     *
     * @return RouteInterface
     */
    private function mergeAttributes(RouteInterface $route): RouteInterface
    {
        foreach ($this->attributes as $type => $attributes) {
            if (Router::TYPE_DEFAULT === $type) {
                $route->setDefaults($attributes);

                continue;
            }

            $route->setPatterns($attributes);
        }

        return $route;
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
