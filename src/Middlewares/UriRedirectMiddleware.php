<?php

declare(strict_types=1);

/*
 * This file is part of Flight Routing.
 *
 * PHP version 8.0 and above required
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 Divine Niiquaye Ibok (https://divinenii.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flight\Routing\Middlewares;

use Flight\Routing\Handlers\RouteHandler;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface, UriInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};

/**
 * The importance of this middleware is to slowly migrate users from old routes
 * to new route paths, with or without maintaining route attributes.
 *
 * Eg:
 * 1. Redirect from `/users/\d+` to `/account`
 * 2. Redirect from `/sign-up` tp `/register`
 * 3. Redirect from `/admin-page` to `#/admin`. The `#` before means, all existing slashes and/or queries are maintained.
 *
 * NB: Old route paths as treated as regex otherwise actual path redirecting to new paths.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class UriRedirectMiddleware implements MiddlewareInterface
{
    /**
     * @param array<string,string|UriInterface> $redirects         [from previous => to new]
     * @param bool                              $keepRequestMethod Whether redirect action should keep HTTP request method
     */
    public function __construct(protected array $redirects = [], private bool $keepRequestMethod = false)
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
        $requestPath = ($uri = $request->getUri())->getPath();

        if ('' === $redirectedUri = (string) ($this->redirects[$requestPath] ?? '')) {
            foreach ($this->redirects as $oldPath => $newPath) {
                if (1 === \preg_match('#^'.$oldPath.'$#u', $requestPath)) {
                    $redirectedUri = $newPath;

                    break;
                }
            }

            if (empty($redirectedUri)) {
                return $handler->handle($request);
            }
        }

        if (\is_string($redirectedUri) && '#' === $redirectedUri[0]) {
            $redirectedUri = $uri->withPath(\substr($redirectedUri, 1));
        }

        return $handler->handle($request->withAttribute(RouteHandler::OVERRIDE_NULL_ROUTE, true))
            ->withStatus($this->keepRequestMethod ? 308 : 301)
            ->withHeader('Location', (string) $redirectedUri)
        ;
    }
}
