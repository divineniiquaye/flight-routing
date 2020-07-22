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

namespace Flight\Routing\Middlewares;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class UriRedirectMiddleware implements MiddlewareInterface
{
    /** @var array */
    protected $redirects = [];

    /** @var bool */
    private $permanent;

    /** @var bool */
    private $query;

    /**
     * @param array<string,string> $redirects [from => to]
     * @param array<string,bool> $options
     */
    public function __construct(array $redirects = [], bool $query = true, bool $permanent = true)
    {
        if (!empty($redirects)) {
            $this->redirects = $redirects;
        }

        $this->query = $query;
        $this->permanent = $permanent;
    }

    /**
     * Whether return a permanent redirect.
     *
     * @param bool $permanent
     *
     * @return UriRedirectMiddleware
     */
    public function setPermanentRedirection(bool $permanent = true): self
    {
        $this->permanent = $permanent;

        return $this;
    }

    /**
     * Whether include the query to search the url.
     *
     * @param bool $query
     *
     * @return UriRedirectMiddleware
     */
    public function allowQueries(bool $query = true): self
    {
        $this->query = $query;

        return $this;
    }

    /**
     * Process a request and return a response.
     * {@inheritdoc}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var ResponseInterface $response */
        $response = $handler->handle($request);

        $uri = $request->getUri()->getPath();

        if ($this->query && ($query = $request->getUri()->getQuery()) !== '') {
            $uri .= '?' . $query;
        }

        if (!isset($this->redirects[$uri])) {
            return $response;
        }

        return $response
            ->withStatus($this->determineResponseCode($request))
            ->withAddedHeader('Location', $this->redirects[$uri]);
    }

    /**
     * Determine the response code according with the method and the permanent config.
     *
     * @param ServerRequestInterface $request
     *
     * @return int
     */
    private function determineResponseCode(ServerRequestInterface $request): int
    {
        if (\in_array($request->getMethod(), ['GET', 'HEAD', 'CONNECT', 'TRACE', 'OPTIONS'])) {
            return $this->permanent ? 301 : 302;
        }

        return $this->permanent ? 308 : 307;
    }
}
