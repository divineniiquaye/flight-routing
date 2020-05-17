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

namespace Flight\Routing\Exceptions;

use DomainException;
use Flight\Routing\Interfaces\ExceptionInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class InvalidMiddlewareException.
 */
class InvalidMiddlewareException extends DomainException implements ExceptionInterface
{
    /**
     * @param mixed $middleware The middleware that does not fulfill the
     *                          expectations of MiddlewarePipe::pipe
     */
    public static function forMiddleware($middleware): self
    {
        return new self(sprintf(
            'Middleware "%s" is neither a string service name, a PHP callable,'
            .' a %s instance, a %s instance, or an array of such arguments',
            is_object($middleware) ? get_class($middleware) : gettype($middleware),
            MiddlewareInterface::class,
            RequestHandlerInterface::class
        ));
    }
}
