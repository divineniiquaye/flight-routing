<?php declare(strict_types=1);

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

namespace Flight\Routing\Interfaces;

use Flight\Routing\Exceptions\UrlGenerationException;
use Flight\Routing\RouteUri;

/**
 * A fluent implementation for reversing compiled route paths.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
interface UrlGeneratorInterface
{
    /**
     * Generate a URI from the named route.
     *
     * Takes the named route and any parameters, and attempts to generate a
     * URI from it. Additional router-dependent query may be passed.
     *
     * Once there are no missing parameters in the URI we will encode
     * the URI and prepare it for returning to the user. If the URI is supposed to
     * be absolute, we will return it as-is. Otherwise we will remove the URL's root.
     *
     * @param string                       $routeName  route name
     * @param array<int|string,int|string> $parameters key => value option pairs to pass to the
     *                                                 router for purposes of generating a URI;
     *                                                 takes precedence over options
     *                                                 present in route used to generate URI
     *
     * @throws UrlGenerationException if the route name is not known
     *                                or a parameter value does not match its regex
     */
    public function generateUri(string $routeName, array $parameters = [], int $referenceType = RouteUri::ABSOLUTE_PATH): RouteUri;
}
