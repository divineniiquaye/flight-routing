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
use Flight\Routing\Concerns\CallableHandler;
use Flight\Routing\Interfaces\RouteGroupInterface;
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
     * {@inheritdoc}
     */
    public function getController()
    {
        return $this->controller;
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

        $namespace = $this->groups[RouteGroupInterface::NAMESPACE] ?? $this->namespace;

        // Append a group namespace starting with a "\" to main namespace.
        if (null !== $namespace && '\\' === $namespace[0]) {
            $namespace = $this->namespace . ltrim($namespace, '\\') . '\\';
        }

        if (
            (is_string($controller) && !class_exists($controller)) &&
            (null !== $namespace && false === strpos($controller, $namespace)
        )) {
            $controller = $namespace . $controller;
        }

        if (
            (!$controller instanceof Closure && is_array($controller)) &&
            (!is_object($controller[0]) && !class_exists($controller[0]))
        ) {
            $controller[0] = $namespace . $controller[0];
        }

        $this->controller = $controller;
    }

    /**
     * Handles a callable controller served on a route
     *
     * @param callable $controller
     * @param ServerRequestInterface $request
     *
     * @return mixed
     * @throws ReflectionException
     */
    protected function handleController(callable $controller, ServerRequestInterface $request)
    {
        $callableHandler = new CallableHandler(
            function ($request, $response) use ($controller) {
                if (class_exists(BoundMethod::class)) {
                    return BoundMethod::call(
                        $this->callableResolver->getContainer(),
                        $controller,
                        $this->arguments + [$request, $response]
                    );
                }

                return $controller($request, $response, $this->arguments);
            },
            ($this->response)()
        );

        return $callableHandler->handle($request);
    }
}
