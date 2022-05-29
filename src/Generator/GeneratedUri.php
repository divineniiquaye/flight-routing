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

namespace Flight\Routing\Generator;

use Flight\Routing\Exceptions\UrlGenerationException;

/**
 * A generated URI from route made up of only the
 * URIs path component (pathinfo, scheme, host, and maybe port.) starting with a slash.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class GeneratedUri implements \Stringable
{
    /** Generates an absolute URL, e.g. "http://example.com/dir/file". */
    public const ABSOLUTE_URL = 0;

    /** Generates an absolute path, e.g. "/dir/file". */
    public const ABSOLUTE_PATH = 1;

    /** Generates a path with beginning with a single dot, e.g. "./file". */
    public const RELATIVE_PATH = 2;

    /** Generates a network path, e.g. "//example.com/dir/file". */
    public const NETWORK_PATH = 3;

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

    private string $pathInfo;
    private int $referenceType;
    private ?string $scheme = null, $host = null, $port = null;

    public function __construct(string $pathInfo, int $referenceType)
    {
        $this->pathInfo = $pathInfo;
        $this->referenceType = $referenceType;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        $prefixed = '/';
        $type = $this->referenceType;

        if ($this->scheme) {
            $prefixed = $this->scheme . '://';
        }

        if ($this->host) {
            if ('/' === $prefixed) {
                $prefixed = \in_array($type, [self::ABSOLUTE_URL, self::NETWORK_PATH], true) ? '//' : '';
            }

            $prefixed .= \ltrim($this->host, './') . $this->port . '/';
        } elseif ('/' === $prefixed && self::RELATIVE_PATH === $type) {
            $prefixed = '.' . $prefixed;
        }

        return $prefixed . \ltrim($this->pathInfo, '/');
    }

    /**
     * Set the host component of the URI, may include port too.
     */
    public function withHost(string $host): self
    {
        $this->host = $host;

        return $this;
    }

    /**
     * Set the scheme component of the URI.
     */
    public function withScheme(string $scheme): self
    {
        $this->scheme = $scheme;

        return $this;
    }

    /**
     * Sets the port component of the URI.
     */
    public function withPort(int $port): self
    {
        if (0 > $port || 0xffff < $port) {
            throw new UrlGenerationException(\sprintf('Invalid port: %d. Must be between 0 and 65535', $port));
        }

        if (!\in_array($port, ['', 80, 443], true)) {
            $this->port = ':' . $port;
        }

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

            if (!empty($queryString)) {
                $this->pathInfo .= '?' . \strtr($queryString, self::QUERY_DECODED);
            }

        }

        return $this;
    }
}
