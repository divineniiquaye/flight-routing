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
use Flight\Routing\Interfaces\RouteListInterface;
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
    use Traits\RouterTrait;

    public const TYPE_REQUIREMENT = 1;

    public const TYPE_DEFAULT = 0;

    public const TYPE_CACHE = 2;

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

            $this->routes[$name] = $this->mergeAttributes($route);

            if (null !== $this->debug) {
                $this->debug->addProfile(new DebugRoute($name, $route));
            }
        }
    }

    /**
     * Gets the router routes
     *
     * @return RouteListInterface
     */
    public function getCollection(): RouteListInterface
    {
        $collection = new RouteList();
        $collection->addForeach(...array_values($this->routes));

        return $collection;
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
        $route = $this->getMatcher()->match($this->getCollection(), $request);

        if (!$route instanceof RouteInterface) {
            throw new RouteNotFoundException(
                \sprintf(
                    'Unable to find the controller for path "%s". The route is wrongly configured.',
                    $request->getUri()->getPath()
                )
            );
        }

        $route->setArguments($this->mergeDefaults($route));

        if (null !== $this->debug) {
            $this->debug->setMatched(new DebugRoute($route->getName(), $route));
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

        $middlewareDispatcher = new Middlewares\MiddlewareDispatcher($this->resolver->getContainer());

        return $middlewareDispatcher->dispatch(
            $this->getMiddlewares(),
            new Handlers\CallbackHandler(
                function (ServerRequestInterface $request) use ($middlewareDispatcher): ResponseInterface {
                    try {
                        $route   = $request->getAttribute(Route::class, $this->route);
                        $handler = $this->resolveHandler($route);
                        $mididlewars = $this->resolveMiddlewares($route);

                        return $middlewareDispatcher->dispatch($mididlewars, $handler, $request);
                    } finally {
                        if (null !== $this->debug) {
                            foreach ($this->debug->getProfiles() as $profiler) {
                                $profiler->leave();
                            }
                        }
                    }
                }
            ),
            $request->withAttribute(Route::class, $this->route)
        );
    }

    /**
     * Gets the RouteMatcherInterface instance associated with this Router.
     *
     * @return RouteMatcherInterface
     */
    public function getMatcher(): RouteMatcherInterface
    {
        $cacheFile = $this->attributes[self::TYPE_CACHE] ?? '';

        if (is_null($this->debug) && (!empty($cacheFile) && !is_file($cacheFile))) {
            $this->generateCompiledRoutes($cacheFile);
        }

        if (file_exists($cacheFile)) {
            $this->matcher->warmCompiler($cacheFile);
        }

        return $this->matcher;
    }
}
