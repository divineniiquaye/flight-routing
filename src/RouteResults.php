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

namespace Flight\Routing;

use Flight\Routing\Concerns\CallableHandler;
use Flight\Routing\Exceptions\MethodNotAllowedException;
use Flight\Routing\Exceptions\RouteNotFoundException;
use Flight\Routing\Interfaces\CallableResolverInterface;
use Flight\Routing\Interfaces\RouteInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * Value object representing the results of routing.
 * RouteResult instances are consumed by the RouteCollector instance.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class RouteResults implements RequestHandlerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public const NOT_FOUND = 0;

    public const FOUND = 1;

    public const METHOD_NOT_ALLOWED = 2;

    /**
     * @var null|string
     */
    protected $redirectUri;

    /**
     * @var int
     *          The status is one of the constants shown above
     *          NOT_FOUND = 0
     *          FOUND = 1
     *          METHOD_NOT_ALLOWED = 2
     */
    protected $routeStatus;

    /**
     * @var array<int|string,mixed>
     */
    protected $routeArguments;

    /**
     * Add this to keep the HTTP method when redirecting.
     *
     * @var int
     */
    protected $keepRequestMethod = 302;

    /**
     * @var null|RouteInterface
     */
    protected $routeIdentifier;

    /**
     * @var callable
     */
    protected $response;

    /**
     * @var CallableResolverInterface
     */
    protected $callableResolver;

    /**
     * @param int                 $routeStatus
     * @param array               $routeArguments
     * @param null|RouteInterface $routeIdentifier
     */
    public function __construct(int $routeStatus, array $routeArguments = [], ?RouteInterface $routeIdentifier = null)
    {
        $this->routeStatus     = $routeStatus;
        $this->routeArguments  = $routeArguments;
        $this->routeIdentifier = $routeIdentifier;
    }

    /**
     * @return int
     */
    public function getRouteStatus(): int
    {
        return $this->routeStatus;
    }

    /**
     * @return null|string
     */
    public function getRedirectLink(): ?string
    {
        return $this->redirectUri;
    }

    /**
     * @param string $uriPath
     *
     * @return $this|self
     */
    public function shouldRedirect(string $uriPath): self
    {
        $this->redirectUri = $uriPath;

        return $this;
    }

    /**
     * Retrieve the allowed methods for the route failure.
     *
     * @return null|string[] HTTP methods allowed
     */
    public function getAllowedMethods(): ?array
    {
        if (null !== $this->routeIdentifier) {
            return $this->routeIdentifier->getMethods();
        }

        return null;
    }

    /**
     * Process the result as requestHandler.
     * If the result represents a failure, it passes handling to the handler.
     *
     * Otherwise, it processes the composed handle using the provide request
     * and handler.
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (self::METHOD_NOT_ALLOWED === $this->routeStatus) {
            throw new MethodNotAllowedException(
                $this->getAllowedMethods() ?? ['other'],
                $request->getUri()->getPath(),
                $request->getMethod()
            );
        }

        // Inject the actual route result, as well as return the response.
        if (!($route = $this->getMatchedRoute()) instanceof RouteInterface) {
            throw new  RouteNotFoundException(
                \sprintf(
                    'Unable to find the controller for path "%s". The route is wrongly configured.',
                    $request->getUri()->getPath()
                )
            );
        }

        // Log the response, so developer can see results.
        if (null !== $this->logger) {
            $this->logRoute($route, $request);
        }

        $response = $this->dispatchRoute($route, $request);

        // Allow Redirection if exists and avoid static request.
        if (null !== $this->getRedirectLink()) {
            return $response
                ->withAddedHeader('Location', $this->getRedirectLink())
                ->withStatus($this->keepRequestMethod);
        }

        return $response;
    }

    /**
     * @param bool $urlDecode
     *
     * @return array<string,mixed>
     */
    public function getRouteArguments(bool $urlDecode = true): array
    {
        $routeArguments = [];

        foreach ($this->routeArguments as $key => $value) {
            if (\is_int($key)) {
                continue;
            }

            $value                = \is_numeric($value) ? (int) $value : $value;
            $routeArguments[$key] = (\is_string($value) && $urlDecode) ? \rawurldecode($value) : $value;
        }

        return $routeArguments;
    }

    /**
     * Retrieve the route that resulted in the route match.
     *
     * @param bool $urlDecode
     *
     * @return null|bool|RouteInterface false if representing a routing failure;
     *                                  null if not created. Route instance otherwise.
     */
    public function getMatchedRoute(bool $urlDecode = true)
    {
        if (null !== $route = $this->routeIdentifier) {
            // Add the arguments
            $route                = $route->addArguments($this->getRouteArguments($urlDecode));
            $this->routeArguments = $route->getArguments();
        }

        return self::FOUND === $this->routeStatus ? $route : (bool) self::NOT_FOUND;
    }

    /**
     * Create a RouteCollector binding for a given callback.
     *
     * @param ServerRequestInterface    $request
     * @param bool                      $status
     * @param CallableResolverInterface $callableResolver
     * @param ResponseFactoryInterface  $responseFactory
     */
    public function bindTo(
        ServerRequestInterface $request,
        bool $status,
        CallableResolverInterface $callableResolver,
        ResponseFactoryInterface $responseFactory
    ): void {
        $this->determineRedirectCode($request, $status);

        $this->callableResolver = $callableResolver;
        $this->response         = [$responseFactory, 'createResponse'];
    }

    /**
     * Handles a request and produces a response.
     *
     * @param RouteInterface         $route
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    private function dispatchRoute(RouteInterface $route, ServerRequestInterface $request): ResponseInterface
    {
        $callableResolver = clone $this->callableResolver;
        $callable         = $callableResolver->resolve($route->getController());

        $request = $request
            ->withAttribute(__CLASS__, $this)
            ->withAttribute('arguments', $route->getArguments());

        // If controller is instance of RequestHandlerInterface
        if (is_array($callable) && $callable[0] instanceof RequestHandlerInterface) {
            return $callable($request);
        }

        $handler = new CallableHandler($route->handle($callable, $callableResolver), ($this->response)());

        return $handler->handle($request);
    }

    /**
     * Determine the response code according with the method and the permanent config.
     *
     * @param ServerRequestInterface $request
     * @param bool $status
     */
    private function determineRedirectCode(ServerRequestInterface $request, bool $status): void
    {
        if (\in_array($request->getMethod(), ['GET', 'HEAD', 'CONNECT', 'TRACE', 'OPTIONS'], true)) {
            $this->keepRequestMethod = $status ? 301 : 302;

            return;
        }

        $this->keepRequestMethod = $status ? 308 : 307;
    }

    private function logRoute(RouteInterface $route, ServerRequestInterface $request): void
    {
        $requestUri = \sprintf(
            '%s://%s%s',
            $request->getUri()->getScheme(),
            $request->getUri()->getHost(),
            $request->getUri()->getPath()
        );

        $this->logger->info('Matched route "{route}".', [
            'route'             => $route->getName() ?? $route->getPath(),
            'route_parameters'  => $route->getArguments(),
            'request_uri'       => $requestUri,
            'method'            => $request->getMethod(),
            'client'            => $request->getServerParams()['REMOTE_ADDR'] ?? '127.0.0.1',
        ]);
    }
}
