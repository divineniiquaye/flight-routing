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

use function in_array;
use function stripos;

/**
 * Fix Content-Type.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class ContentTypeMiddeware implements MiddlewareInterface
{
    /**
     * @param ServerRequestInterface  $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$request->hasHeader('Content-Type') && in_array($request->getMethod(), ['POST', 'PUT', 'DELETE'])) {
            $request = $request->withAttribute('Content-Type', 'application/x-www-form-urlencoded');
        }

        if (
            $request->getMethod() === 'POST' &&
            in_array($request->getHeader('Content-Type'), ['application/x-www-form-urlencoded', 'multipart/form-data'])
        ) {
            $request = $request->withParsedBody($_POST);
        }


        /** @var ResponseInterface $response */
        $response = $handler->handle($request);

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

        return $response;
    }
}
