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
     * @var null|callable|object|string|string[]
     */
    protected $controller;

    /**
     * Route parameters.
     *
     * @var array<string,mixed>
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
        $controller = $this->controller;
        $namespace  = $this->groups[RouteGroupInterface::NAMESPACE] ?? $this->namespace;

        // Append a group namespace starting with a "\" to main namespace.
        if (null !== $namespace && '\\' === $namespace[0]) {
            $namespace = $this->namespace . \ltrim((string) $namespace, '\\') . '\\';
        }

        if (null !== $namespace && (\is_string($controller) || !$controller instanceof Closure)) {
            return $this->appendNamespace($controller, (string) $namespace);
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
        return function (Request $request, Response $response) use ($controller, $callableResolver) {
            if (\class_exists(BoundMethod::class)) {
                return BoundMethod::call(
                    $container = $callableResolver->getContainer(),
                    $controller,
                    \array_merge($this->arguments, $container ? [$request] : [$request, $response])
                );
            }

            return $controller($request, $response, $this->arguments);
        };
    }

    /**
     * @param null|callable|object|string|string[] $controller
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
     * @param null|callable|object|string|string[] $controller
     * @param string                               $namespace
     *
     * @return null|callable|object|string|string[]
     */
    private function appendNamespace($controller, string $namespace)
    {
        if (\is_string($controller) && !\class_exists($controller) && false === \stripos($controller, $namespace)) {
            $controller = \is_callable($controller) ? $controller : $namespace . $controller;
        }

        if (\is_array($controller) && (!\is_object($controller[0]) && !\class_exists($controller[0]))) {
            $controller[0] = $namespace . $controller[0];
        }

        return $controller;
    }
}
