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

use Flight\Routing\Interfaces\RouteInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

use function assert;
use function method_exists;
use function rawurldecode;

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
     * @var string
     */
    protected $method;

    /**
     * @var string
     */
    protected $uri;

    /**
     * @var string|null
     */
    protected $redirectUri;

    /**
     * @var int
     * The status is one of the constants shown above
     * NOT_FOUND = 0
     * FOUND = 1
     * METHOD_NOT_ALLOWED = 2
     */
    protected $routeStatus;

    /**
     * @var RouteInterface|string
     */
    protected $routeIdentifier;

    /**
     * @var array
     */
    protected $routeArguments;

    /**
     * @param string              $method
     * @param string              $uri
     * @param int                 $routeStatus
     * @param string|null         $routeIdentifier
     * @param array               $routeArguments
     */
    public function __construct(
        string $method,
        string $uri,
        int $routeStatus,
        array $routeArguments = [],
        ?RouteInterface $routeIdentifier = null
    ) {
        $this->method = $method;
        $this->uri = $uri;
        $this->routeStatus = $routeStatus;
        $this->routeArguments = $routeArguments;
        $this->routeIdentifier = $routeIdentifier;
    }

    /**
     * @return RouteInterface|null
     */
    public function getRouteIdentifier(bool $urlDecode = true): ?RouteInterface
    {
        if (null !== $route = $this->routeIdentifier) {
            $this->routeIdentifier = $route->prepare($this->getRouteArguments($urlDecode));
        }
        return $this->routeIdentifier;
    }

    /**
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @return string
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * @return int
     */
    public function getRouteStatus(): int
    {
        return $this->routeStatus;
    }

    /**
     * @return bool
     */
    public function getRedirectLink(): ?string
    {
        return $this->redirectUri;
    }

    /**
     * @return $this|self
     */
    public function shouldRedirect(string $uriPath): RouteResults
    {
        $this->redirectUri = $uriPath;

        return $this;
    }

    /**
     * Retrieve the allowed methods for the route failure.
     *
     * @return null|string[] HTTP methods allowed
     */
    public function getAllowedMethods() : ?array
    {
        if (null !== $this->getRouteIdentifier()) {
            return $this->getRouteIdentifier()->getMethods();
        }

        return null;
    }

    /**
     * Process the result as requestHandler.
     *
     * If the result represents a failure, it passes handling to the handler.
     *
     * Otherwise, it processes the composed handle using the provide request
     * and handler.
     */
    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        // Inject the actual route result, as well as return the response.
        $route = $this->getRouteIdentifier();
        assert($route instanceof RequestHandlerInterface);

        // Log the response, so developer can see results.
        if (null !== $this->logger) {
            $this->logRoute($route, $request);
        }

        // The Route response.
        $response = $route->run($request->withAttribute('routingResults', $this));

        if (null !== $newUri = $this->getRedirectLink()) {
            $response = $response->withStatus(301)
                ->withAddedHeader('Location', $newUri);
        }

        return $response;
    }

    /**
     * @param bool $urlDecode
     * @return array
     */
    public function getRouteArguments(bool $urlDecode = true): array
    {
        if (!$urlDecode) {
            return $this->routeArguments;
        }

        $routeArguments = [];
        foreach ($this->routeArguments as $key => $value) {
            $routeArguments[$key] = rawurldecode($value);
        }

        return $routeArguments;
    }

    private function logRoute(RouteInterface $route, ServerRequestInterface $request)
    {
        $requestUri = method_exists($request, 'getUriForPath')
            ? $request->getUriForPath($request->getUri()->getPath())
            : $request->getUri()->getHost() . $request->getUri()->getPath();

        $requestIp = method_exists($request, 'getRemoteAddress')
        ? $request->getRemoteAddress()
        : $request->getServerParams()['REMOTE_ADDR'];

        $this->logger->info('Matched route "{route}".', [
            'route'             => $route->getName() ?? $route->getPath(),
            'route_parameters'  => $route->getArguments(),
            'request_uri'       => $requestUri,
            'method'            => $request->getMethod(),
            'client'            => $requestIp
        ]);
    }
}
