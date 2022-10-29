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

namespace Flight\Routing\Handlers;

use Flight\Routing\Exceptions\{InvalidControllerException, RouteNotFoundException};
use Flight\Routing\Router;
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
    /** This allows a response to be served when no route is found. */
    public const OVERRIDE_NULL_ROUTE = 'OVERRIDE_NULL_ROUTE';

    /** @var callable */
    protected $handlerResolver;

    public function __construct(protected ResponseFactoryInterface $responseFactory, callable $handlerResolver = null)
    {
        $this->handlerResolver = $handlerResolver ?? new RouteInvoker();
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidControllerException|RouteNotFoundException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (null === $route = $request->getAttribute(Router::class)) {
            if (true === $res = $request->getAttribute(static::OVERRIDE_NULL_ROUTE)) {
                return $this->responseFactory->createResponse();
            }

            return $res instanceof ResponseInterface ? $res : throw new RouteNotFoundException($request->getUri());
        }

        if (empty($handler = $route['handler'] ?? null)) {
            return $this->responseFactory->createResponse(204)->withHeader('Content-Type', 'text/plain; charset=utf-8');
        }

        $arguments = fn (ServerRequestInterface $request): array => $this->resolveArguments($request, $route['arguments'] ?? []);
        $response = RouteInvoker::resolveRoute($request, $this->handlerResolver, $handler, $arguments);

        if ($response instanceof FileHandler) {
            return $response($this->responseFactory);
        }

        if (!$response instanceof ResponseInterface) {
            if (empty($contents = $response)) {
                throw new InvalidControllerException('The route handler\'s content is not a valid PSR7 response body stream.');
            }
            ($response = $this->responseFactory->createResponse())->getBody()->write($contents);
        }

        return $response->hasHeader('Content-Type') ? $response : $this->negotiateContentType($response);
    }

    /**
     * A HTTP response Content-Type header negotiator for html, json, svg, xml, and plain-text.
     */
    protected function negotiateContentType(ResponseInterface $response): ResponseInterface
    {
        if (empty($contents = (string) $response->getBody())) {
            $mime = 'text/plain; charset=utf-8';
            $response = $response->withStatus(204);
        } elseif (false === $mime = (new \finfo(\FILEINFO_MIME_TYPE))->buffer($contents)) {
            $mime = 'text/html; charset=utf-8'; // @codeCoverageIgnore
        } elseif ('text/xml' === $mime) {
            \preg_match('/<(?:\s+)?\/?(?:\s+)?(\w+)(?:\s+)?>$/', $contents, $xml, \PREG_UNMATCHED_AS_NULL);
            $mime = 'svg' === $xml[1] ? 'image/svg+xml' : \sprintf('%s; charset=utf-8', 'rss' === $xml[1] ? 'application/rss+xml' : 'text/xml');
        }

        return $response->withHeader('Content-Type', $mime);
    }

    /**
     * @param array<string,mixed> $parameters
     *
     * @return array<int|string,mixed>
     */
    protected function resolveArguments(ServerRequestInterface $request, array $parameters): array
    {
        foreach ([$request, $this->responseFactory] as $psr7) {
            $parameters[$psr7::class] = $psr7;

            foreach ((@\class_implements($psr7) ?: []) as $psr7Interface) {
                $parameters[$psr7Interface] = $psr7;
            }
        }

        return $parameters;
    }
}
