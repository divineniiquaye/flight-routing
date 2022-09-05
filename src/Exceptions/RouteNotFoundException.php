<?php

declare(strict_types=1);

/*
 * This file is part of Flight Routing.
 *
 * PHP version 7.4 and above required
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 Biurad Group (https://biurad.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flight\Routing\Exceptions;

use Flight\Routing\Interfaces\ExceptionInterface;
use Psr\Http\Message\UriInterface;

/**
 * Class RouteNotFoundException.
 */
class RouteNotFoundException extends \DomainException implements ExceptionInterface
{
    public function __construct(string|UriInterface $message = '', int $code = 404, \Throwable $previous = null)
    {
        if ($message instanceof UriInterface) {
            $message = \sprintf('Unable to find the controller for path "%s". The route is wrongly configured.', $message->getPath());
        }

        parent::__construct($message, $code, $previous);
    }
}
