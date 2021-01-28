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
use Fig\Http\Message\RequestMethodInterface;
use Flight\Routing\Exceptions\DuplicateRouteException;
use Flight\Routing\Exceptions\MethodNotAllowedException;
use Flight\Routing\Exceptions\RouteNotFoundException;
use Flight\Routing\Exceptions\UriHandlerException;
use Flight\Routing\Exceptions\UrlGenerationException;
use Flight\Routing\Interfaces\RouteMatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Aggregate routes for matching and Dispatching.
 * 
 * Internally, the class performs some checks for duplicate routes when
 * attaching via one of the exposed methods, and will raise an exception when a
 * collision occurs.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class Router implements RequestHandlerInterface, RequestMethodInterface
{
    use Traits\RouterTrait;

    /**
     * Standard HTTP methods for browser requests.
     */
    public const HTTP_METHODS_STANDARD = [
        self::METHOD_HEAD,
        self::METHOD_GET,
        self::METHOD_POST,
        self::METHOD_PUT,
        self::METHOD_PATCH,
        self::METHOD_DELETE,
        self::METHOD_PURGE,
        self::METHOD_OPTIONS,
        self::METHOD_TRACE,
        self::METHOD_CONNECT,
    ];

    public const TYPE_REQUIREMENT = 1;

    public const TYPE_DEFAULT = 0;

    public const TYPE_CACHE = 2;

    private const TYPE_MATCHER = 3;

    public function __construct(
        ResponseFactoryInterface $responseFactory,
        UriFactoryInterface $uriFactory,
        ?string $matcher = null,
        ?InvokerInterface $resolver = null
    ) {
        $this->uriFactory      = $uriFactory;
        $this->responseFactory = $responseFactory;
        $this->resolver        = $resolver ?? new Invoker();

        $this->routes  = new RouteList();
        $this->attributes[self::TYPE_MATCHER] = $matcher ?? Matchers\SimpleRouteMatcher::class;
    }

    /**
     * Adds the given route(s) to the router
     *
     * @param Route ...$routes
     *
     * @throws DuplicateRouteException
     */
    public function addRoute(Route ...$routes): void
    {
        foreach ($routes as $route) {
            if (null === $name = $route->getName()) {
                $route->bind($name = $route->generateRouteName(''));
            }

            if (null !== $this->routes->find($name)) {
                throw new DuplicateRouteException(
                    \sprintf('A route with the name "%s" already exists.', $name)
                );
            }

            $this->routes->add($this->mergeAttributes($route));

            if (null !== $this->debug) {
                $this->debug->addProfile(new DebugRoute($name, $route));
            }
        }
    }

    /**
     * Gets the router routes
     *
     * @return RouteList
     */
    public function getCollection(): RouteList
    {
        return $this->routes;
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
     * @return Route
     */
    public function match(ServerRequestInterface $request): Route
    {
        // Get the request matching format.
        $route = $this->getMatcher()->match($request);

        if (!$route instanceof Route) {
            throw new RouteNotFoundException(
                \sprintf(
                    'Unable to find the controller for path "%s". The route is wrongly configured.',
                    $request->getUri()->getPath()
                )
            );
        }

        $this->mergeDefaults($route);

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
        if (!$this->route instanceof Route) {
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
        if (null !== $this->matcher) {
            return $this->matcher;
        }

        $cacheFile = $this->attributes[self::TYPE_CACHE] ?? '';
        $matcher = $this->attributes[self::TYPE_MATCHER];

        if (null === $this->debug && (!empty($cacheFile) && \is_string($cacheFile))) {
            if (!file_exists($cacheFile)) {
                $this->generateCompiledRoutes($cacheFile, $matcher = new $matcher($this->getCollection()));

                return $this->matcher = $matcher;
            }

            return $this->matcher = new $matcher($cacheFile);
        }

        return $this->matcher = new $matcher($this->getCollection());
    }
}
