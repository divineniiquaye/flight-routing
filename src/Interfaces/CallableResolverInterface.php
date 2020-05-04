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

namespace Flight\Routing\Interfaces;

use Flight\Routing\Exceptions\InvalidControllerException;
use Psr\Http\Message\ResponseInterface;

interface CallableResolverInterface
{
    /**
     * This instance added will be binded to CLosure
     *
     * @param object $instance
     * @return CallableResolverInterface
     */
    public function addInstanceToClosure($instance): CallableResolverInterface;

    /**
     * Resolve toResolve into a callable that the router can dispatch.
     *
     * If toResolve is of the format 'class:method', 'class::method',
     * and 'class@method', then try to extract 'class' from the container
     * otherwise instantiate it and then dispatch 'method'.
     *
     * @param mixed $toResolve
     * @return callable
     *
     * @throws InvalidControllerException if the callable does not exist
     * @throws InvalidControllerException if the callable is not resolvable
     */
    public function resolve($toResolve): callable;

    /**
     * Resolves the return type of a route controller to
     * PSR 7 ResponseInterface, with the best content-type.
     * content-type fallback is text/html.
     *
     * @param string|int|array|ResponseInterface $controllerResponse
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     */
    public function returnType($controllerResponse, ResponseInterface $response): ResponseInterface;
}
