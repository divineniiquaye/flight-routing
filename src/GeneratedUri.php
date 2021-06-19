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

namespace Flight\Routing;

/**
 * A generated URI from route made up of only the
 * URIs path component (pathinfo, scheme, host, and maybe port.) starting with a slash.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class GeneratedUri implements \Stringable
{
    /** Adopted from symfony's routing component: Symfony\Component\Routing\Generator::QUERY_FRAGMENT_DECODED */
    private const QUERY_DECODED = [
        // RFC 3986 explicitly allows those in the query to reference other URIs unencoded
        '%2F' => '/',
        '%3F' => '?',
        // reserved chars that have no special meaning for HTTP URIs in a query
        // this excludes esp. "&", "=" and also "+" because PHP would treat it as a space (form-encoded)
        '%40' => '@',
        '%3A' => ':',
        '%21' => '!',
        '%3B' => ';',
        '%2C' => ',',
        '%2A' => '*',
    ];

    /** @var string */
    private $pathInfo;

    /** @var string|null */
    private $scheme = null;

    /** @var string|null */
    private $host = null;

    public function __construct(string $pathInfo)
    {
        $this->pathInfo = $pathInfo;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        $uriRoute = $this->scheme . $this->host . $this->pathInfo;

        if (!\str_contains($uriRoute, '://')) {
            $prefix = '.'; // Append missing "." at the beginning of the $uri.

            if ('/' !== @$uriRoute[0]) {
                $prefix .= '/';
            }

            $uriRoute = $prefix . $uriRoute;
        }

        return $uriRoute;
    }

    /**
     * Set the host component of the URI, may include port too.
     */
    public function withHost(string $host): self
    {
        $this->host = '' !== $host ? '//' . $host : null;

        return $this;
    }

    /**
     * Set the scheme component of the URI.
     */
    public function withScheme(string $scheme): self
    {
        $this->scheme = '' !== $scheme ? $scheme . ':' : null;

        return $this;
    }

    /**
     * Set the query component of the URI.
     *
     * @param array<int|string,int|string> $queryParams
     */
    public function withQuery(array $queryParams = []): self
    {
        // Incase query is added to uri.
        if ([] !== $queryParams) {
            $queryString = \http_build_query($queryParams, '', '&', \PHP_QUERY_RFC3986);

            $this->pathInfo .= '?' . \strtr($queryString, self::QUERY_DECODED);
        }

        return $this;
    }
}