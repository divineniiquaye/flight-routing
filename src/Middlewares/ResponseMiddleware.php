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
use Psr\Http\Server\MiddlewareInterface;
use BiuradPHP\Http\Exceptions\ClientException;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

/**
 * Default response dispatch middleware.
 *
 * Checks for a composed route result in the request. If none is provided,
 * delegates request processing to the handler.
 *
 * Otherwise, it delegates processing to the route result.
 */
class ResponseMiddleware implements MiddlewareInterface
{
    /**
     * {@inheritDoc}
     *
     * @param Request $request
     * @param RequestHandler $handler
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function process(Request $request, RequestHandler $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        // Set the cache control to no cache
        // disable caching of potentially sensitive data
        if (!$response->hasHeader('Cache-Control')) {
            $response = $response
                ->withHeader('Cache-Control', 'private, no-cache, must-revalidate, no-store')
            ;
        }

        // prevent content sniffing (MIME sniffing)
        if (!$response->hasHeader('X-Content-Type-Options')) {
            //$response = $response
            //    ->withHeader('X-Content-Type-Options', 'nosniff')
            //;
        }

        // Fix Content-Type
        if (!$response->hasHeader('Content-Type')) {
            $response = $response
                ->withHeader('Content-Type', 'text/html; charset=UTF-8');
        } elseif (
            0 === stripos($response->getHeaderLine('Content-Type'), 'text/') &&
            false === stripos($response->getHeaderLine('Content-Type'), 'charset')) {
            // add the charset
            $response = $response
                ->withHeader('Content-Type', $response->getHeaderLine('Content-Type').'; charset=UTF-8');
        }

        // Check if we need to send extra expire info headers
        if (
            $response->getProtocolVersion() &&
            false !== strpos($response->getHeaderLine('Cache-Control'), 'no-cache')
        ) {
            $response = $response
                ->withHeader('Pragma', 'no-cache')
                ->withHeader('expires', 'Thu, 19 Nov 1981 00:00:00 GMT');
        }

        // Redirection header
        if ($response->getStatusCode() === 302) {
            $response = $response
                ->withoutHeader('Cache-Control')
                ->withAddedHeader('Cache-Control', 'no-cache, must-revalidate');
        }

        // Incase response is empty
        if ($this->isResponseEmpty($response)) {
            $response = $response
                ->withoutHeader('Allow')
                ->withoutHeader('Content-MD5')
                ->withoutHeader('Content-Type')
                ->withoutHeader('Content-Length');
        }

         // Handle Headers Error
         if ($response->getStatusCode() >= 400) {
            for ($i = 400; $i < 600; $i++) {
                if ($response->getStatusCode() === $i) {
                    throw new ClientException($i);
                }
            };
        }

        // remove headers that MUST NOT be included with 304 Not Modified responses
        return $response;
    }

    /**
     * Asserts response body is empty or status code is 204, 205 or 304
     *
     * @param ResponseInterface $response
     * @return bool
     */
    private function isResponseEmpty(ResponseInterface $response): bool
    {
        $contents = (string) $response->getBody();

        return empty($contents) || in_array($response->getStatusCode(), [204, 205, 304], true);
    }
}
