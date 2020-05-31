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

namespace Flight\Routing\Traits;

use BiuradPHP\Support\BoundMethod;
use Closure;
use Flight\Routing\Interfaces\CallableResolverInterface;
use Flight\Routing\Interfaces\RouteGroupInterface;
use Flight\Routing\Interfaces\RouteInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
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
     * Route callable
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
        $this->arguments = array_merge($arguments, $this->arguments);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getArgument(string $name, ?string $default = null): ?string
    {
        if (array_key_exists($name, $this->arguments)) {
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
            $namespace = $this->namespace . ltrim($namespace, '\\') . '\\';
        }

        if (is_string(($controller = $this->controller)) || is_array($controller)) {
            return $this->appendNamespace($controller, $namespace);
        }

        return $controller;
    }

    /**
     * @param callable|string|object|null $controller
     *
     * @return void
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
     * Handles a callable controller served on a route
     *
     * @param callable $controller
     * @param CallableResolverInterface $callableResolver
     *
     * @return callable
     * @throws ReflectionException
     */
    public function handle(callable $controller, CallableResolverInterface $callableResolver): callable
    {
        $finalController = function (ServerRequestInterface $request, ResponseInterface $response) use ($controller, $callableResolver) {
            if (class_exists(BoundMethod::class)) {
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
     * @param callable|string|object|null $controller
     * @param string|null $namespace
     *
     * @return mixed
     */
    private function appendNamespace($controller, ?string $namespace)
    {
        if (
            (is_string($controller) && !class_exists($controller)) &&
            (null !== $namespace && false === strpos($controller, $namespace)
        )) {
            $controller = is_callable($controller) ? $controller : $namespace . $controller;
        }

        if (
            (!$controller instanceof Closure && is_array($controller)) &&
            (!is_object($controller[0]) && !class_exists($controller[0]))
        ) {
            $controller[0] = $namespace . $controller[0];
        }

        return $controller;
    }
}
