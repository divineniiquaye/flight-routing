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

use BiuradPHP\Http\Exceptions\HttpException;
use BiuradPHP\Http\Exceptions\ClientExceptions\NotFoundException;

/**
 * Class RouteNotFoundException
 */
class RouteNotFoundException extends \DomainException
{
    /**
     * The Router Exception
     *
     * @param string $message
     */
    public function __construct(string $message = '')
    {
        if (empty($message) && class_exists(HttpException::class)) {
            throw new NotFoundException();
        }
        return parent::__construct($message, 404, $this->getPrevious());
    }
}
