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

namespace Flight\Routing\Concerns;

use Fig\Http\Message\RequestMethodInterface;

/**
 * Class HttpMethods.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class HttpMethods implements RequestMethodInterface
{
    /**
     * Standard HTTP methods against which to test HEAD/OPTIONS requests.
     */
    public const HTTP_METHODS_STANDARD = [
        self::METHOD_HEAD,
        self::METHOD_GET,
        self::METHOD_POST,
        self::METHOD_PUT,
        self::METHOD_PATCH,
        self::METHOD_DELETE,
        self::METHOD_PURGE,
        self::METHOD_OPTIONS,
        self::METHOD_TRACE,
        self::METHOD_CONNECT,
    ];

    /**
     * Standardize custom http method name
     * For the methods that are not defined in this enum.
     *
     * @param string $method
     *
     * @return string
     */
    public static function custom(string $method): string
    {
        return \strtoupper($method);
    }
}
