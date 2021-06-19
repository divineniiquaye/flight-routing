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

use Psr\Http\Message\UriInterface;

/**
 * The request context to match route.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class RequestContext implements \Stringable
{
    /** @var string */
    private $pathInfo;

    /** @var string */
    private $method;

    /** @var UriInterface */
    private $uri;

    /**
     * @param string $pathInfo should be the PATH_INFO from server request
     * @param string $method   the HTTP request method
     */
    public function __construct(string $pathInfo, string $method, UriInterface $uri)
    {
        $this->pathInfo = $pathInfo;
        $this->method = $method;
        $this->uri = $uri;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        return $this->method . $this->uri->getScheme() . '://' . $this->getHost() . $this->getPathInfo();
    }

    public function getPathInfo(): string
    {
        // Resolve request path to match sub-directory or /index.php/path
        if (empty($resolvedPath = $this->pathInfo)) {
            $resolvedPath = $this->uri->getPath();
        }

        if ('/' !== $resolvedPath && isset(Route::URL_PREFIX_SLASHES[$resolvedPath[-1]])) {
            $resolvedPath = \substr($resolvedPath, 0, -1);
        }

        return $resolvedPath;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getScheme(): string
    {
        return $this->uri->getScheme();
    }

    public function getHost(): string
    {
        $hostAndPort = $this->uri->getHost();

        // Added port to host for matching ...
        if (null !== $this->uri->getPort()) {
            $hostAndPort .= ':' . $this->uri->getPort();
        }

        return $hostAndPort;
    }
}
