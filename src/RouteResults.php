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
 *
 * DispatcherInterface::dispatch() is defined as returning a RouteResult instance,
 * which will contain the following state:
 *
 * - On success, it will contain:
 *   - the matched route name (typically the path)
 *   - the matched route middleware
 *   - any parameters matched by routing
 *   - RouteInterface instance
 * - On failure:
 *   - This further qualifies a routing failure to indicate that it
 *     was due to using an HTTP method not allowed for the given path.
 *   - If the failure was not due to HTTP method negotiation, it will contain a
 *     not found exception thrown.
 *
 * RouteResult instances are consumed by the DispatcherInterface in the routing
 * RouteCollector instance.
 */
class RouteResults implements RequestHandlerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public const NOT_FOUND = 0;
    public const FOUND = 1;
    public const METHOD_NOT_ALLOWED = 2;

    /**
     * @var string|null
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
     * @var array
     */
    protected $routeArguments;

    /**
     * Add this to keep the HTTP method when redirecting.
     *
     * @var int
     */
    protected $keepRequestMethod = 302;

    /**
     * @var RouteInterface|string
     */
    protected $routeIdentifier;

    /**
     * @var ResponseFactoryInterface
     */
    protected $response;

    /**
     * @var CallableResolverInterface
     */
    protected $callableResolver;

    /**
     * @param int                 $routeStatus
     * @param array               $routeArguments
     * @param RouteInterface|null $routeIdentifier
     */
    public function __construct(int $routeStatus, array $routeArguments = [], ?RouteInterface $routeIdentifier = null)
    {
        $this->routeStatus = $routeStatus;
        $this->routeArguments = $routeArguments;
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
     * @return string|null
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
            throw new MethodNotAllowedException($this->getAllowedMethods() ?? ['other'], $request->getUri()->getPath(), $request->getMethod());
        }

        // Inject the actual route result, as well as return the response.
        if (false === $route = $this->getMatchedRoute()) {
            throw new  RouteNotFoundException(
                sprintf('Unable to find the controller for path "%s". The route is wrongly configured.', $request->getUri()->getPath())
            );
        }

        // Log the response, so developer can see results.
        if (null !== $this->logger) {
            $this->logRoute($route, $request);
        }

        $response = $this->dispatchRoute($this->getMatchedRoute(), $request);

        // Allow Redirection if exists and avoid static request.
        if (null !== $this->getRedirectLink()) {
            return $response->withAddedHeader('Location', $this->getRedirectLink())
                ->withStatus($this->keepRequestMethod);
        }

        return $response;
    }

    /**
     * @param bool $urlDecode
     *
     * @return array
     */
    public function getRouteArguments(bool $urlDecode = true): array
    {
        $routeArguments = [];
        foreach ($this->routeArguments as $key => $value) {
            if (is_int($key)) {
                continue;
            }

            $value = is_numeric($value) ? (int) $value : $value;
            $routeArguments[$key] = (is_string($value) && $urlDecode) ? rawurldecode($value) : $value;
        }

        return $routeArguments;
    }

    /**
     * Retrieve the route that resulted in the route match.
     *
     * @param bool $urlDecode
     *
     * @return false|RouteInterface|RequestHandlerInterface false if representing a routing failure;
     *                                                      null if not created. Route instance otherwise.
     */
    public function getMatchedRoute(bool $urlDecode = true)
    {
        if (null !== $route = $this->routeIdentifier) {
            // Add the arguments
            $route = $route->addArguments($this->getRouteArguments($urlDecode));
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
     *
     * @return void
     */
    public function bindTo(
        ServerRequestInterface $request,
        bool $status,
        CallableResolverInterface $callableResolver,
        ResponseFactoryInterface $responseFactory
    ) {
        $this->determineRedirectCode($request, $status);

        $this->callableResolver = $callableResolver;
        $this->response = [$responseFactory, 'createResponse'];
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
        $callable = $callableResolver->resolve($route->getController());

        $request = $request
            ->withAttribute(__CLASS__, $this)
            ->withAttribute('arguments', $route->getArguments());

        // If controller is instance of RequestHandlerInterface
        if (!$callable instanceof \Closure && $callable[0] instanceof RequestHandlerInterface) {
            return $callable($request);
        }

        return (new CallableHandler($route->handle($callable, $callableResolver), ($this->response)()))->handle($request);
    }

    /**
     * Determine the response code according with the method and the permanent config.
     *
     * @param ServerRequestInterface $request
     * @param bool status
     */
    private function determineRedirectCode(ServerRequestInterface $request, bool $status): void
    {
        if (in_array($request->getMethod(), ['GET', 'HEAD', 'CONNECT', 'TRACE', 'OPTIONS'], true)) {
            $this->keepRequestMethod = $status ? 301 : 302;

            return;
        }

        $this->keepRequestMethod = $status ? 308 : 307;
    }

    private function logRoute(RouteInterface $route, ServerRequestInterface $request)
    {
        $requestUri = sprintf(
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
