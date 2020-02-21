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

namespace Flight\Routing\Concerns;

use Fig\Http\Message\RequestMethodInterface;

/**
 * Class HttpMethods
 */
class HttpMethods implements RequestMethodInterface
{
    /**
     * Standard HTTP methods against which to test HEAD/OPTIONS requests.
     */
    public const HTTP_METHODS_STANDARD = [
        HttpMethods::METHOD_HEAD,
        HttpMethods::METHOD_GET,
        HttpMethods::METHOD_POST,
        HttpMethods::METHOD_PUT,
        HttpMethods::METHOD_PATCH,
        HttpMethods::METHOD_DELETE,
        HttpMethods::METHOD_PURGE,
        HttpMethods::METHOD_OPTIONS,
        HttpMethods::METHOD_TRACE,
        HttpMethods::METHOD_CONNECT,
    ];

    /**
     * Standardize custom http method name
     * For the methods that are not defined in this enum
     *
     * @param string $method
     * @return string
     */
    public static function custom(string $method): string
    {
        return strtoupper($method);
    }
}
