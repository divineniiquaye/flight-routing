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

namespace Flight\Routing\Middlewares;

use Closure;
use Flight\Routing\RouteCollector;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Default routing request handler.
 *
 * Uses the composed router to match against the incoming request, and
 * injects the request passed to the handler with the `Class@method` instance
 * returned (using the `Routing` class name and it's method).
 *
 * If routing succeeds, injects the request passed to the handler with any
 * matched parameters as well.
 */
class RouteRequestHandler implements RequestHandlerInterface
{
    private $next, $router, $arguments;

    /**
     * @param \BiuradPHP\Routing\Router         $router
     * @param Closure|callable|string           $controller
     * @param array                             $parameters
     */
    public function __construct(RouteCollector $router, $controller, $parameters)
    {
        $this->router = $router;
        $this->next = $controller;
        $this->arguments = $parameters;
    }

    /**
     * {@inheritdoc}
     *
     * This request handler is instantiated automatically.
     * It is at the very tip of the middleware queue meaning it will be executed
     * last and it detects whether or not routing has been performed in the user
     * defined middleware stack. In the event that the user did not perform routing
     * it is done here
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->router->runController(
            $this->next, $this->arguments, $request
        );
    }
}
