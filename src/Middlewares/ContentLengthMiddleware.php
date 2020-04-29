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

namespace Flight\Routing\Middlewares;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Allow to set the correct content length header on the request or to transfer it as a chunk if not possible.
 *
 * @author Joel Wurtz <joel.wurtz@gmail.com>
 */
class ContentLengthMiddleware implements MiddlewareInterface
{
    /**
     * @param ServerRequestInterface  $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Add Content-Length header if not already added
        if (!$request->hasHeader('Content-Length')) {
            $stream = $request->getBody();

            // Cannot determine the size so we use a chunk stream
            if (null === $stream->getSize()) {
                $request = $request->withAddedHeader('Transfer-Encoding', 'chunked');
            } else {
                $request = $request->withHeader('Content-Length', (string) $stream->getSize());
            }
        }

        // The response instance.
        $response = $handler->handle($request);

        // Fix Content-Length
        if ($response->hasHeader('Transfer-Encoding')) {
            $response = $response->withoutHeader('Content-Length');
        }

        if ('HEAD' === $request->getMethod()) {
            // cf. RFC2616 14.13
            $length = $response->getHeaderLine('Content-Length');
            if (null !== $length = $response->getBody()->getSize()) {
                $response = $response->withHeader('Content-Length', (string) $length);
            }
        }

        return $response;
    }
}
