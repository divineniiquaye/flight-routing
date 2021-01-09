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
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Router
 */
class Router implements RequestHandlerInterface
{
    use Traits\ResolveTrait;
    use Traits\ValidationTrait;
    use Traits\MiddlewareTrait;
    use Traits\RouterTrait;

    public const TYPE_REQUIREMENT = 1;

    public const TYPE_DEFAULT = 0;

    public function __construct(
        ResponseFactoryInterface $responseFactory,
        UriFactoryInterface $uriFactory,
        ?RouteMatcherInterface $matcher = null,
        ?InvokerInterface $resolver = null
    ) {
        $this->uriFactory      = $uriFactory;
        $this->responseFactory = $responseFactory;
        $this->resolver        = $resolver ?? new Invoker();
        $this->matcher         = $matcher ?? new Matchers\SimpleRouteMatcher();
    }

    public function __clone()
    {
        foreach ($this->routes as $name => $route) {
            $this->routes[$name] = clone $route;
        }
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

            if ($this->profiler instanceof DebugRoute) {
                $this->profiler->addProfile(new DebugRoute($name, $route));
            }

            $this->routes[$name] = $route;
        }
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
     * @return UriInterface of fully qualified URL for named route
     */
    public function generateUri(string $routeName, array $parameters = [], array $queryParams = []): UriInterface
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

        return $this->uriFactory->createUri($this->resolveUri($route, $parameters, $queryParams));
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
     * @return RouteInterface
     */
    public function match(ServerRequestInterface $request): RouteInterface
    {
        // Get the request matching format.
        $route = $this->marshalMatchedRoute(
            $request->getMethod(),
            $requestUri = $request->getUri(),
            $this->resolvePath($request, $requestUri->getPath())
        );

        if (!$route instanceof RouteInterface) {
            throw new RouteNotFoundException(
                \sprintf(
                    'Unable to find the controller for path "%s". The route is wrongly configured.',
                    $requestUri->getPath()
                )
            );
        }
        $route->setController($this->resolveController($request, $route));

        // Run listeners on route not more than once ...
        if (null === $this->route) {
            foreach ($this->listeners as $listener) {
                $listener->onRoute($request, $route);
            }
        }

        if ($this->profiler instanceof DebugRoute) {
            $this->profiler->setMatched($route->getName());
        }

        return $this->route = clone $route;
    }

    /**
     * {@inheritDoc}
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Get the Route Handler ready for dispatching
        if (!$this->route instanceof RouteInterface) {
            $this->match($request);
        }

        return ($handler = new Middlewares\MiddlewareDispatcher($this->resolver->getContainer()))->dispatch(
            $this->getMiddlewares(),
            new Handlers\CallbackHandler(function (ServerRequestInterface $request) use ($handler): ResponseInterface {
                try {
                    $route   = $request->getAttribute(Route::class, $this->route);
                    $routeHandler = $this->resolveRoute($route);

                    return $handler->dispatch($this->resolveMiddlewares($route), $routeHandler, $request);
                } finally {
                    if ($this->profiler instanceof DebugRoute) {
                        foreach ($this->profiler->getProfiles() as $profiler) {
                            $profiler->leave();
                        }
                    }
                }
            }),
            $request->withAttribute(Route::class, $this->route),
        );
    }

    /**
     * Marshals a route result based on the results of matching URL from set of routes.
     *
     * @param string $method
     * @param UriInterface $uri
     * @param string $path
     *
     * @throws MethodNotAllowedException
     * @throws UriHandlerException
     *
     * @return null|RouteInterface
     */
    private function marshalMatchedRoute(string $method, UriInterface $uri, string $path): ?RouteInterface
    {
        foreach ($this->routes as $route) {
            // Let's match the routes
            $match         = $this->matcher->compileRoute($this->mergeAttributes($route));
            $uriParameters = $hostParameters = [];

            // https://tools.ietf.org/html/rfc7231#section-6.5.5
            if (!$this->compareUri($match->getRegex(), $path, $uriParameters)) {
                continue;
            }

            if (!$this->compareDomain($match->getRegex(true), $uri->getHost(), $hostParameters)) {
                throw new UriHandlerException(
                    \sprintf('Unfortunately current domain "%s" is not allowed on requested uri [%s]', $uri->getHost(), $path),
                    400
                );
            }
            $this->assertRoute($route, [$method, $uri->getScheme(), $path]);

            $parameters = \array_replace($match->getVariables(), $uriParameters, $hostParameters);

            return $route->setArguments($this->mergeDefaults($parameters, $route->getDefaults()));
        }

        return null;
    }
}
