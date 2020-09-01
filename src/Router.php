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

use DivineNii\Invoker\Interfaces\InvokerInterface;
use DivineNii\Invoker\Invoker;
use Flight\Routing\Exceptions\DuplicateRouteException;
use Flight\Routing\Exceptions\MethodNotAllowedException;
use Flight\Routing\Exceptions\RouteNotFoundException;
use Flight\Routing\Exceptions\UriHandlerException;
use Flight\Routing\Exceptions\UrlGenerationException;
use Flight\Routing\Interfaces\RouteInterface;
use Flight\Routing\Interfaces\RouteMatcherInterface;
use Flight\Routing\Middlewares\MiddlewareDispatcher;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Router
 */
class Router implements RequestHandlerInterface
{
    use Traits\ValidationTrait;

    public const TYPE_REQUIREMENT = 1;

    public const TYPE_DEFAULT = 0;

    /** @var RouteMatcherInterface */
    private $matcher;

    /** @var InvokerInterface */
    private $resolver;

    /** @var MiddlewareDispatcher */
    private $pipeline;

    /** @var null|ContainerInterface */
    private $container;

    /** @var callable */
    private $response;

    /** @var string */
    private $namespace;

    /** @var RouteInterface[] */
    private $routes = [];

    /** @var array<string,mixed> */
    private $middlewares = [];

    /** @var array<int,array<string,mixed>> */
    private $attributes = [];

    public function __construct(
        ResponseFactoryInterface $responseFactory,
        ?RouteMatcherInterface $matcher = null,
        ?InvokerInterface $resolver = null,
        ?ContainerInterface $container = null
    ) {
        $this->container = $container;
        $this->pipeline  = new MiddlewareDispatcher($container);
        $this->resolver  = $resolver ?? new Invoker([], $container);
        $this->matcher   = $matcher ?? new Services\SimpleRouteMatcher();

        $this->response = [$responseFactory, 'createResponse'];
    }

    /**
     * Gets the router routes
     *
     * @return RouteInterface[]
     */
    public function getRoutes(): array
    {
        return \array_values($this->routes);
    }

    /**
     * Gets the router middlewares
     *
     * @return array<int,array<array<string,mixed>|MiddlewareInterface|string>>
     */
    public function getMiddlewares(): array
    {
        return \array_values($this->middlewares);
    }

    public function setNamespace(string $namespace): void
    {
        $this->namespace = $namespace;
    }

    /**
     * Adds the given route(s) to the router
     *
     * @param RouteInterface ...$routes
     *
     * @throws DuplicateRouteException
     */
    public function addRoute(RouteInterface ...$routes): void
    {
        foreach ($routes as $route) {
            $name = $route->getName();

            if (isset($this->routes[$name])) {
                throw new DuplicateRouteException(
                    \sprintf('A route with the name "%s" already exists.', $name)
                );
            }

            $this->routes[$name] = $route;
        }
    }

    /**
     * Adds the given middleware(s) to the router
     *
     * @param array<string,mixed>|MiddlewareInterface|string ...$middlewares
     *
     * @throws DuplicateRouteException
     */
    public function addMiddleware(...$middlewares): void
    {
        foreach ($middlewares as $middleware) {
            if (\is_array($middleware) || \is_callable($middleware)) {
                $this->pipeline->add($middleware);

                continue;
            }
            $hash = \is_object($middleware) ? \spl_object_hash($middleware) : \md5($middleware);

            if (isset($this->middlewares[$hash])) {
                throw new DuplicateRouteException(\sprintf('A middleware with the hash "%s" already exists.', $hash));
            }

            $this->middlewares[$hash] = $middleware;
        }
    }

    /**
     * Adds parameters.
     *
     * This method implements a fluent interface.
     *
     * @param array<string,mixed> $parameters The parameters
     * @param int                 $type
     */
    public function addParameters(array $parameters, int $type = self::TYPE_REQUIREMENT): void
    {
        foreach ($parameters as $key => $regex) {
            if (self::TYPE_DEFAULT === $type) {
                $this->attributes[self::TYPE_DEFAULT] = [$key => $regex];

                continue;
            }

            $this->attributes[self::TYPE_REQUIREMENT] = [$key => $regex];
        }
    }

    /**
     * Gets allowed methods
     *
     * @return string[]
     */
    public function getAllowedMethods(): array
    {
        $methods = [];

        foreach ($this->routes as $route) {
            foreach ($route->getMethods() as $method) {
                $methods[$method] = true;
            }
        }

        return \array_keys($methods);
    }

    /**
     * Gets a route for the given name
     *
     * @param string $name
     *
     * @throws RouteNotFoundException
     *
     * @return RouteInterface
     */
    public function getRoute(string $name): RouteInterface
    {
        if (!isset($this->routes[$name])) {
            throw new RouteNotFoundException(\sprintf('No route found for the name "%s".', $name));
        }

        return $this->routes[$name];
    }

    /**
     * Generate a URI from the named route.
     *
     * Takes the named route and any parameters, and attempts to generate a
     * URI from it. Additional router-dependent query may be passed.
     *
     * Once there are no missing parameters in the URI we will encode
     * the URI and prepare it for returning to the user. If the URI is supposed to
     * be absolute, we will return it as-is. Otherwise we will remove the URL's root.
     *
     * @param string                       $routeName   route name
     * @param array<string,string>         $parameters  key => value option pairs to pass to the
     *                                                  router for purposes of generating a URI; takes precedence over options
     *                                                  present in route used to generate URI
     * @param array<int|string,int|string> $queryParams Optional query string parameters
     *
     * @throws UrlGenerationException if the route name is not known
     *                                or a parameter value does not match its regex
     *
     * @return string of fully qualified URL for named route
     */
    public function generateUri(string $routeName, array $parameters = [], array $queryParams = []): string
    {
        try {
            $route = $this->getRoute($routeName);
        } catch (RouteNotFoundException $e) {
            throw new UrlGenerationException(
                \sprintf(
                    'Unable to generate a URL for the named route "%s" as such route does not exist.',
                    $routeName
                ),
                404
            );
        }

        $prefix     = '.'; // Append missing "." at the beginning of the $uri.
        $createdUri = $this->matcher->buildPath($route, $parameters);

        // Making routing on sub-folders easier
        if (\strpos($createdUri = $this->matcher->buildPath($route, $parameters), '/') !== 0) {
            $prefix .= '/';
        }

        // Incase query is added to uri.
        if (!empty($queryParams)) {
            $createdUri .= '?' . \http_build_query($queryParams);
        }

        return \rtrim(\strpos($createdUri, '://') !== false ? $createdUri : $prefix . $createdUri, '/');
    }

    /**
     * Looks for a route that matches the given request
     *
     * @param ServerRequestInterface $request
     *
     * @throws MethodNotAllowedException
     * @throws UriHandlerException
     * @throws RouteNotFoundException
     *
     * @return RouteHandler
     */
    public function match(ServerRequestInterface &$request): RouteHandler
    {
        $requestUri  = $request->getUri();
        $basePath    = \dirname($request->getServerParams()['SCRIPT_NAME'] ?? '');
        $requestPath = \substr($requestUri->getPath(), \strlen($basePath)) ?? '/';

        // Get the request matching format.
        $route = $this->marshalMatchedRoute(
            [
                $request->getMethod(),
                $requestUri->getScheme(),
                $requestUri->getHost(),
                \rawurldecode($requestPath),
            ]
        );
        $request = $request->withAttribute(Route::class, $route);

        return new RouteHandler($this->generateResponse($route), ($this->response)());
    }

    /**
     * {@inheritDoc}
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $routingResults = $this->match($request);
        $route          = $request->getAttribute(Route::class);

        // Get all available middlewares
        $middlewares = $route instanceof Route ? $route->getMiddlewares() : [];

        if (\count($middlewares = $this->mergeMiddlewares($middlewares)) > 0) {
            $middleware = $this->pipeline->pipeline(...$middlewares);

            // This middleware is in the priority map; but, this is the first middleware we have
            // encountered from the map thus far. We'll save its current index plus its index
            // from the priority map so we can compare against them on the next iterations.
            return $middleware->process($request, $routingResults);
        }

        return $routingResults->handle($request);
    }

    /**
     * Merge route middlewares with Router Middlewares.
     *
     * @param mixed[] $middlewares
     *
     * @return MiddlewareInterface[]|mixed[]
     */
    private function mergeMiddlewares(array $middlewares): array
    {
        $this->pipeline->add(...$this->getMiddlewares());

        return \array_merge($middlewares, $this->pipeline->getMiddlewareStack());
    }

    /**
     * Generate the response so it can be served
     *
     * @param RouteInterface $route
     *
     * @return callable
     */
    private function generateResponse(RouteInterface $route): callable
    {
        return function (ServerRequestInterface $request, ResponseInterface $response) use ($route) {
            $controller = $route->getController();

            // Disable or enable HTTP request method prefix for action.
            if (\is_array($controller) && false !== \strpos($route->getName(), '__restful')) {
                $controller[1] = \strtolower($request->getMethod()) . \ucfirst($controller[1]);
            }

            $handler   = $this->resolveController($controller);
            $arguments = [ServerRequestInterface::class => $request, ResponseInterface::class => $response];

            // For a class that implements RequestHandlerInterface, we will call handle()
            // if no method has been specified explicitly
            if (\is_string($handler) && \is_a($handler, RequestHandlerInterface::class, true)) {
                $handler = [$handler, 'handle'];
            }

            // If controller is instance of RequestHandlerInterface
            if ($handler instanceof RequestHandlerInterface) {
                return $handler->handle($request);
            }

            return $this->resolver->call($handler, \array_merge($route->getArguments(), $arguments));
        };
    }

    /**
     * Marshals a route result based on the results of matching URL from set of routes.
     *
     * @param string[] $process
     *
     * @throws MethodNotAllowedException
     * @throws UriHandlerException
     * @throws RouteNotFoundException
     *
     * @return RouteInterface
     */
    private function marshalMatchedRoute(array $process): RouteInterface
    {
        foreach ($this->routes as $route) {
            // Let's match the routes
            $match      = $this->matcher->compileRoute($this->mergeAttributes($route));
            $parameters = $hostParameters = [];

            // https://tools.ietf.org/html/rfc7231#section-6.5.5
            if (!$this->compareUri($match->getRegex(), $process[3], $parameters)) {
                continue;
            }

            $this->assertRoute($route, $match->getRegex(true), $hostParameters, $process);

            return $route->setArguments($this->mergeDefaults(
                \array_replace($parameters, $hostParameters) ?? $match->getVariables(),
                $route->getDefaults()
            ));
        }

        throw new  RouteNotFoundException(
            \sprintf(
                'Unable to find the controller for path "%s". The route is wrongly configured.',
                $process[3]
            )
        );
    }

    /**
     * Asserts the Route's method and domain scheme.
     *
     * @param RouteInterface           $route
     * @param string                   $domain
     * @param array<int|string,string> $parameters
     * @param array<int,mixed>         $attributes
     */
    private function assertRoute(RouteInterface $route, string $domain, array &$parameters, array $attributes): void
    {
        [$method, $scheme, $host, $path] = $attributes;
        $parameters                      = [];

        if (!$this->compareMethod($route->getMethods(), $method)) {
            throw new MethodNotAllowedException($route->getMethods(), $path, $method);
        }

        if (!$this->compareDomain($domain, $host, $parameters)) {
            throw new UriHandlerException(
                \sprintf('Unfortunately current domain "%s" is not allowed on requested uri [%s]', $host, $path),
                400
            );
        }

        if (!$this->compareScheme($route->getSchemes(), $scheme)) {
            throw new UriHandlerException(
                \sprintf('Unfortunately current scheme "%s" is not allowed on requested uri [%s]', $scheme, $path),
                400
            );
        }
    }
}
