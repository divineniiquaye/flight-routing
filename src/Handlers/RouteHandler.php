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

namespace Flight\Routing\Handlers;

use Flight\Routing\{Exceptions\RouteNotFoundException, Route};
use Psr\Http\Message\{ResponseFactoryInterface, ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Default routing request handler.
 *
 * if route is found in request attribute, dispatch the route handler's
 * response to the browser and provides ability to detect right response content-type.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
final class RouteHandler implements RequestHandlerInterface
{
    public const CONTENT_TYPE = 'Content-Type';

    /** @var ResponseFactoryInterface */
    private $responseFactory;

    /** @var null|callable(mixed,array) */
    private $handlerResolver = null;

    public function __construct(ResponseFactoryInterface $responseFactory, callable $handlerResolver = null)
    {
        $this->handlerResolver = $handlerResolver;
        $this->responseFactory = $responseFactory;
    }

    /**
     * {@inheritdoc}
     *
     * @throws RouteNotFoundException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $route = $request->getAttribute(Route::class);

        if (!$route instanceof Route) {
            throw new RouteNotFoundException(\sprintf('Unable to find the controller for path "%s". The route is wrongly configured.', $request->getUri()->getPath()));
        }

        // Resolve route handler arguments ...
        if (null !== $handlerResolver = $this->handlerResolver) {
            $route->arguments([\get_class($request) => $request, \get_class($this->responseFactory) => $this->responseFactory]);
        } else {
            foreach ([$request, $this->responseFactory] as $psr7) {
                foreach (@\class_implements($psr7) ?: [] as $psr7Interface) {
                    $route->argument($psr7Interface, $psr7);
                }

                $route->argument(\get_class($psr7), $psr7);
            }
        }

        $response = $route($request, $handlerResolver);

        if (!$response instanceof ResponseInterface) {
            ($result = $this->responseFactory->createResponse())->getBody()->write($response);
            $response = $result;
        }

        if ($response->hasHeader(self::CONTENT_TYPE)) {
            return $response;
        }

        $contents = (string) $response->getBody();
        $matched = \preg_match('#(?|\"\w\"\:.*?\,?\}|^\<\?(xml).*|[^>]+\>.*?\<\/(\w+)\>)$#ms', $contents, $matches);

        if (!$matchedType = $matches[2] ?? $matches[1] ?? 0 !== $matched) {
            return $response->withHeader(self::CONTENT_TYPE, '{' === @$contents[0] ? 'application/json' : 'text/plain; charset=utf-8');
        }

        if ('svg' === $matchedType) {
            $xmlResponse = $response->withHeader(self::CONTENT_TYPE, 'image/svg+xml');
        } elseif ('xml' === $matchedType) {
            $xmlResponse = $response->withHeader(self::CONTENT_TYPE, 'application/xml; charset=utf-8');
        }

        return $xmlResponse ?? $response->withHeader(self::CONTENT_TYPE, 'text/html; charset=utf-8');
    }
}
