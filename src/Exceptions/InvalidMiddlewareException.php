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
        return new self(\sprintf(
            'Middleware "%s" is neither a string service name, a PHP callable,'
            . ' a %s instance, a %s instance, or an array of such arguments',
            \is_object($middleware) ? \get_class($middleware) : \gettype($middleware),
            MiddlewareInterface::class,
            RequestHandlerInterface::class
        ));
    }
}
