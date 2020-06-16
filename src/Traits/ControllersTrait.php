<?php

declare(strict_types=1);

/*
 * This file is part of Flight Routing.
 *
 * PHP version 7.2 and above required
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 Biurad Group (https://biurad.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flight\Routing\Traits;

use BiuradPHP\Support\BoundMethod;
use Closure;
use Flight\Routing\Interfaces\CallableResolverInterface;
use Flight\Routing\Interfaces\RouteGroupInterface;
use Flight\Routing\Interfaces\RouteInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use ReflectionException;

trait ControllersTrait
{
    /**
     * Route Default Namespace.
     *
     * @var string
     */
    protected $namespace;

    /**
     * Route callable.
     *
     * @var callable|string
     */
    protected $controller;

    /**
     * Route parameters.
     *
     * @var array
     */
    protected $arguments = [];

    /**
     * {@inheritdoc}
     */
    public function addArguments(array $arguments): RouteInterface
    {
        $this->arguments = \array_merge($arguments, $this->arguments);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getArgument(string $name, ?string $default = null): ?string
    {
        if (\array_key_exists($name, $this->arguments)) {
            return $this->arguments[$name];
        }

        return $default;
    }

    /**
     * {@inheritdoc}
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * {@inheritdoc}
     */
    public function getController()
    {
        $namespace = $this->groups[RouteGroupInterface::NAMESPACE] ?? $this->namespace;

        // Append a group namespace starting with a "\" to main namespace.
        if (null !== $namespace && '\\' === $namespace[0]) {
            $namespace = $this->namespace . \ltrim($namespace, '\\') . '\\';
        }

        if (\is_string(($controller = $this->controller)) || \is_array($controller)) {
            return $this->appendNamespace($controller, $namespace);
        }

        return $controller;
    }

    /**
     * Handles a callable controller served on a route.
     *
     * @param callable                  $controller
     * @param CallableResolverInterface $callableResolver
     *
     * @throws ReflectionException
     *
     * @return Closure
     */
    public function handle(callable $controller, CallableResolverInterface $callableResolver): callable
    {
        $finalController = function (Request $request, Response $response) use ($controller, $callableResolver) {
            if (\class_exists(BoundMethod::class)) {
                return BoundMethod::call(
                    $container = $callableResolver->getContainer(),
                    $controller,
                    $this->arguments + ($container ? [$request] : [$request, $response])
                );
            }

            return $controller($request, $response, $this->arguments);
        };

        return $finalController;
    }

    /**
     * @param null|callable|object|string $controller
     */
    protected function setController($controller): void
    {
        // Might find controller on route pattern.
        if (null === $controller) {
            return;
        }

        $this->controller = $controller;
    }

    /**
     * @param null|callable|object|string $controller
     * @param null|string                 $namespace
     *
     * @return null|callable
     */
    private function appendNamespace($controller, ?string $namespace)
    {
        if (
            \is_string($controller) &&
            (
                !\class_exists($controller) &&
                false === \stripos($controller, (string) $namespace)
            )
        ) {
            $controller = \is_callable($controller) ? $controller : $namespace . $controller;
        }

        if (
            (
                !$controller instanceof Closure &&
                \is_array($controller)
            ) &&
            (
                !\is_object($controller[0]) &&
                !\class_exists($controller[0])
            )
        ) {
            $controller[0] = $namespace . $controller[0];
        }

        return $controller;
    }
}
