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

/**
 * HTTP 405 exception.
 */
class MethodNotAllowedException extends RouteNotFoundException
{
    public function  __construct(array $methods, string $path, string $method)
    {
        $message = 'Unfotunately current uri "%s" is allowed on [%s] request methods, "%s" is invalid';

        parent::__construct(sprintf($message, $path, implode(',', $methods), $method));
    }
}
