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
class RequestContext
{
    /** @var string */
    private $method;

    /** @var UriInterface */
    private $uri;

    /**
     * @param string $pathInfo should be the PATH_INFO from server request,
     *                         this resolves request path to match sub-directory or /index.php/path
     * @param string $method   the HTTP request method
     */
    public function __construct(string $pathInfo, string $method, UriInterface $uri)
    {
        // Resolve request path to match sub-directory or /index.php/path
        if (empty($pathInfo)) {
            $pathInfo = $uri->getPath();
        }

        if ('/' !== $pathInfo && isset(Route::URL_PREFIX_SLASHES[$pathInfo[-1]])) {
            $pathInfo = \substr($pathInfo, 0, -1);
        }

        $this->method = $method;
        $this->uri = $uri->withPath($pathInfo);
    }

    public function getPathInfo(): string
    {
        return $this->uri->getPath();
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
