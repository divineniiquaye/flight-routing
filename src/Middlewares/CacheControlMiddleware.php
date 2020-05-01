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

use function array_change_key_case;
use function array_key_exists;
use function preg_replace;
use function stripos;

use const CASE_LOWER;

/**
 * Set the cache control to no cache
 * Disable's caching of potentially sensitive data.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class CacheControlMiddleware implements MiddlewareInterface
{
    /**
     * @param ServerRequestInterface  $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var ResponseInterface $response */
        $response = $handler->handle($request);

        // Set the cache control to no cache
        // disable caching of potentially sensitive data
        if (!$response->hasHeader('Cache-Control')) {
            $response = $response
                ->withHeader('Cache-Control', 'private, no-cache, must-revalidate, no-store')
            ;
        }

        // Fix protocol
        if ('HTTP/1.0' !== $request->getServerParams()['SERVER_PROTOCOL']) {
            $response = $response->withProtocolVersion('1.1');
        }

        // Checks if we need to remove Cache-Control for SSL encrypted downloads when using IE < 9.
        $response = $this->ensureIEOverSSLCompatibility($request, $response);

        // Check if we need to send extra expire info headers
        if (
            '1.0' === $response->getProtocolVersion() &&
            false !== strpos($response->getHeaderLine('Cache-Control'), 'no-cache')
        ) {
            $response = $response
                ->withHeader('Pragma', 'no-cache')
                ->withHeader('Expires', -1);
        }

        // Redirection headers for 302 and 301
        if ($response->getStatusCode() === 302) {
            $response = $response
                ->withoutHeader('Cache-Control')
                ->withAddedHeader('Cache-Control', 'no-cache, must-revalidate');
        }

        if (
            301 === $response->getStatusCode() &&
            !array_key_exists('cache-control', array_change_key_case($response->getHeaders(), CASE_LOWER))
        ) {
            $response = $response->withoutHeader('Cache-Control');
        }

        return $response;
    }

    /**
     * Checks if we need to remove Cache-Control for SSL encrypted downloads when using IE < 9.
     *
     * @see http://support.microsoft.com/kb/323308
     *
     * @final
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     */
    protected function ensureIEOverSSLCompatibility(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (
            false !== stripos($response->getHeaderLine('Content-Disposition'), 'attachment') &&
            1 === preg_match('/MSIE (.*?);/i', $request->getServerParams()['HTTP_USER_AGENT'], $match) &&
            array_key_exists('HTTPS', $request->getServerParams())
        ) {
            if ((int) preg_replace('/(MSIE )(.*?);/', '$2', $match[0]) < 9) {
                $response = $response->withoutHeader('Cache-Control');
            }
        }

        return $response;
    }
}
