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

use Flight\Routing\Exceptions\UriHandlerException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The importance of this middleware is to keep existing route paths users
 * might be familiarized with, and slowly transition them into new route paths.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class UriRedirectMiddleware implements MiddlewareInterface
{
    /** @var array<string,string|UriInterface> */
    protected $redirects;

    /** @var bool */
    private $keepRequestMethod;

    /**
     * @param array<string,string|UriInterface> $redirects         [from previous => to new]
     * @param bool                              $keepRequestMethod Whether redirect action should keep HTTP request method
     */
    public function __construct(array $redirects = [], bool $keepRequestMethod = false)
    {
        $this->redirects = $redirects;
        $this->keepRequestMethod = $keepRequestMethod;
    }

    /**
     * Process a request and return a response.
     * {@inheritdoc}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $uri = $request->getUri();
        $response = $handler->handle($request);

        if (!isset($this->redirects[$uri->getPath()])) {
            return $response;
        }

        $redirectedUri = $this->redirects[$uri->getPath()];

        if (\is_string($redirectedUri) && \str_contains($redirectedUri, '//')) {
            throw new UriHandlerException(\sprintf('Handling "%s" to a string path "%s" containing a host is not supported, use a %s instance for such purposes.', $uri->getPath(), $redirectedUri, UriInterface::class));
        }

        return $response
            ->withStatus($this->keepRequestMethod ? 308 : 301)
            ->withHeader('Location', (string) ($redirectedUri instanceof UriInterface ? $redirectedUri : $uri->withPath($redirectedUri)));
    }
}
