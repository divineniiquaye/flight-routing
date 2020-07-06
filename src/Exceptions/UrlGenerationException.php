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

namespace Flight\Routing\Exceptions;

use DomainException;
use Flight\Routing\Interfaces\ExceptionInterface;
use Flight\Routing\Interfaces\RouteInterface;

class UrlGenerationException extends DomainException implements ExceptionInterface
{
    /**
     * Create a new exception for missing route parameters.
     *
     * @param RouteInterface $route
     *
     * @return static
     */
    public static function forMissingParameters(RouteInterface $route)
    {
        return new static("Missing required parameters for [Route: {$route->getName()}] [URI: {$route->getPath()}].");
    }
}
