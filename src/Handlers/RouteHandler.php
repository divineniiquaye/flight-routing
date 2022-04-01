<?php

declare(strict_types=1);

/*
 * This file is part of Flight Routing.
 *
 * PHP version 7.4 and above required
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 Biurad Group (https://biurad.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flight\Routing\Handlers;

use Flight\Routing\Route;
use Flight\Routing\Exceptions\{InvalidControllerException, RouteNotFoundException};
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
class RouteHandler implements RequestHandlerInterface
{
    /**
     * This allows a response to be served when no route is found.
     */
    public const OVERRIDE_HTTP_RESPONSE = ResponseInterface::class;

    protected const CONTENT_TYPE = 'Content-Type';
    protected const CONTENT_REGEX = '#(?|\{\"[\w\,\"\:\[\]]+\}|\["[\w\"\,]+\]|\<(?|\?(xml)|\w+).*>.*<\/(\w+)>)$#s';

    protected ResponseFactoryInterface $responseFactory;

    /** @var callable */
    protected $handlerResolver;

    public function __construct(ResponseFactoryInterface $responseFactory, callable $handlerResolver = null)
    {
        $this->responseFactory = $responseFactory;
        $this->handlerResolver = $handlerResolver ?? new RouteInvoker();
    }

    /**
     * {@inheritdoc}
     *
     * @throws RouteNotFoundException|InvalidControllerException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (null === $route = $request->getAttribute(Route::class)) {
            if (true === $notFoundResponse = $request->getAttribute(static::OVERRIDE_HTTP_RESPONSE)) {
                return $this->responseFactory->createResponse();
            }

            if ($notFoundResponse instanceof ResponseInterface) {
                return $notFoundResponse;
            }

            throw new RouteNotFoundException(\sprintf('Unable to find the controller for path "%s". The route is wrongly configured.', $request->getUri()->getPath()), 404);
        }

        // Resolve route handler arguments ...
        if (!$response = $this->resolveRoute($route, $request)) {
            throw new InvalidControllerException('The route handler\'s content is not a valid PSR7 response body stream.');
        }

        if (!$response instanceof ResponseInterface) {
            ($result = $this->responseFactory->createResponse())->getBody()->write($response);
            $response = $result;
        }

        return $response->hasHeader(self::CONTENT_TYPE) ? $response : $this->negotiateContentType($response);
    }

    /**
     * @return ResponseInterface|string|false
     */
    protected function resolveRoute(Route $route, ServerRequestInterface $request)
    {
        \ob_start(); // Start buffering response output

        try {
            // The route handler to resolve ...
            $handler = $route->getHandler();

            if ($handler instanceof ResourceHandler) {
                $handler = $handler($request->getMethod());
            }

            $response = ($this->handlerResolver)($handler, $this->resolveArguments($request, $route));

            if ($response instanceof RequestHandlerInterface) {
                return $response->handle($request);
            }

            if ($response instanceof ResponseInterface || \is_string($response)) {
                return $response;
            }

            if ($response instanceof \JsonSerializable || \is_iterable($response) || \is_array($response)) {
                return \json_encode($response, \JSON_THROW_ON_ERROR);
            }
        } catch (\Throwable $e) {
            \ob_get_clean();

            throw $e;
        } finally {
            while (\ob_get_level() > 1) {
                $response = \ob_get_clean(); // If more than one output buffers is set ...
            }
        }

        return $response ?? \ob_get_clean();
    }

    /**
     * A HTTP response Content-Type header negotiator for html, json, svg, xml, and plain-text.
     */
    protected function negotiateContentType(ResponseInterface $response): ResponseInterface
    {
        $contents = (string) $response->getBody();
        $contentType = 'text/html; charset=utf-8'; // Default content type.

        if (1 === $matched = \preg_match(static::CONTENT_REGEX, $contents, $matches, \PREG_UNMATCHED_AS_NULL)) {
            if (null === $matches[2]) {
                $contentType = 'application/json';
            } elseif ('xml' === $matches[1]) {
                $contentType = 'svg' === $matches[2] ? 'image/svg+xml' : \sprintf('application/%s; charset=utf-8', 'rss' === $matches[2] ? 'rss+xml' : 'xml');
            }
        } elseif (0 === $matched) {
            $contentType = 'text/plain; charset=utf-8';
        }

        return $response->withHeader(self::CONTENT_TYPE, $contentType);
    }

    /**
     * @return array<int|string,mixed>
     */
    protected function resolveArguments(ServerRequestInterface $request, Route $route): array
    {
        $parameters = $route->getArguments();

        foreach ([$request, $this->responseFactory] as $psr7) {
            $parameters[\get_class($psr7)] = $psr7;

            foreach ((@\class_implements($psr7) ?: []) as $psr7Interface) {
                $parameters[$psr7Interface] = $psr7;
            }
        }

        return $parameters;
    }
}
