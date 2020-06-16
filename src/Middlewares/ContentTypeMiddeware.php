<?php

declare(strict_types=1);

/*
 * This file is part of Flight Routing.
 *
 * PHP version 7.2 and above required
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
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$request->hasHeader('Content-Type') && \in_array($request->getMethod(), ['POST', 'PUT', 'DELETE'])) {
            $request = $request->withAttribute('Content-Type', 'application/x-www-form-urlencoded');
        }
        $formContentType = ['application/x-www-form-urlencoded', 'multipart/form-data'];

        if (
            $request->getMethod() === 'POST' &&
            \in_array($request->getHeader('Content-Type'), $formContentType, true)
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
            0 === \stripos($response->getHeaderLine('Content-Type'), 'text/') &&
            false === \stripos($response->getHeaderLine('Content-Type'), 'charset')
        ) {
            // add the charset
            $response = $response
                ->withHeader('Content-Type', $response->getHeaderLine('Content-Type') . '; charset=UTF-8');
        }

        return $response;
    }
}
