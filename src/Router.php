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
use Flight\Routing\Handlers\RouteHandler;
use Flight\Routing\Interfaces\RouteMatcherInterface;
use Flight\Routing\Interfaces\RouterInterface;
use Laminas\Stratigility\MiddlewarePipe;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\MiddlewareInterface;
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
class Router implements RouterInterface, RequestHandlerInterface
{
    use Traits\RouterTrait;

    /** @var MiddlewarePipeInterface */
    private $pipeline;

    public function __construct(
        ResponseFactoryInterface $responseFactory,
        UriFactoryInterface $uriFactory,
        ?InvokerInterface $resolver = null,
        array $options = []
    ) {
        $this->uriFactory      = $uriFactory;
        $this->responseFactory = $responseFactory;
        $this->resolver        = new RouteResolver($resolver ?? new Invoker());

        $this->setOptions($options);
        $this->debug  = new DebugRoute();
        $this->routes = new RouteCollection();

        $this->pipeline = new MiddlewarePipe();
    }

    /**
     * Sets options.
     *
     * Available options:
     *
     *   * cache_dir:              The cache directory (or null to disable caching)
     *   * debug:                  Whether to enable debugging or not (false by default)
     *   * namespace:              Set Namespace for route handlers/controllers
     *   * matcher_class:          The name of a RouteMatcherInterface implementation
     *   * matcher_dumper_class:   The name of a MatcherDumperInterface implementation
     *
     * @throws InvalidArgumentException When unsupported option is provided
     */
    public function setOptions(array $options): void
    {
        $this->options = [
            'cache_dir'            => null,
            'debug'                => false,
            'namespace'            => null,
            'matcher_class'        => Matchers\SimpleRouteMatcher::class,
            'matcher_dumper_class' => CompiledUrlMatcherDumper::class,
        ];

        // check option names and live merge, if errors are encountered Exception will be thrown
        $invalid = [];

        foreach ($options as $key => $value) {
            if (\array_key_exists($key, $this->options)) {
                $this->options[$key] = $value;
            } else {
                $invalid[] = $key;
            }
        }

        if (!empty($invalid)) {
            throw new \InvalidArgumentException(
                \sprintf('The Router does not support the following options: "%s".', \implode('", "', $invalid))
            );
        }
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

            $this->routes->add($route);
        }
    }

    /**
     * Attach middleware to the pipeline.
     *
     * @param array<string,mixed>|callable|MiddlewareInterface|RequestHandlerInterface|string $middleware
     */
    public function pipe($middleware): void
    {
        if (!$middleware instanceof MiddlewareInterface) {
            $middleware = $this->resolveMiddleware($middleware);
        }

        $this->pipeline->pipe($middleware);
    }

    /**
     * {@inheritdoc}
     */
    public function getCollection(): RouteCollection
    {
        return $this->routes;
    }

    /**
     * {@inheritdoc}
     *
     * @return UriInterface of fully qualified URL for named route
     */
    public function generateUri(string $routeName, array $parameters = [], array $queryParams = []): UriInterface
    {
        $createUri = $this->getMatcher()->generateUri($routeName, $parameters, $queryParams);

        return $this->uriFactory->createUri($createUri);
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

        if (isset($this->options['debug']) && null !== $route->getName()) {
            $this->debug->setMatched(new DebugRoute($route->getName(), $route));
        }

        return $this->route = clone $route;
    }

    /**
     * {@inheritDoc}
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $route   = $this->match($request);
        $handler = $route->getController();

        if (!$handler instanceof RequestHandlerInterface) {
            $handler = new RouteHandler(
                function (ServerRequestInterface $request, ResponseInterface $response) use ($route) {
                    if (isset($this->options['namespace'])) {
                        $this->resolver->setNamespace($this->options['namespace']);
                    }

                    return ($this->resolver)($request, $response, $route);
                },
                $this->responseFactory
            );
        }

        try {
            $middleware = $this->resolveMiddlewares(new MiddlewarePipe(), $route);

            return $this->pipeline->process(
                $request->withAttribute(Route::class, $route),
                new Handlers\CallbackHandler(
                    static function (ServerRequestInterface $request) use ($middleware, $handler) {
                        return $middleware->process($request, $handler);
                    }
                )
            );
        } finally {
            if (isset($this->options['debug'])) {
                foreach ($this->debug->getProfiles() as $profiler) {
                    $profiler->leave();
                }
            } else {
                $this->debug->leave();
            }
        }
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

        $cacheFile = $this->options['cache_dir'] ?? '';
        $matcher   = $this->options['matcher_class'];

        if (!isset($this->options['debug']) && (!empty($cacheFile) && \is_string($cacheFile))) {
            if (!\file_exists($cacheFile)) {
                $this->generateCompiledRoutes($cacheFile, $matcher = new $matcher($this->getCollection()));

                return $this->matcher = $matcher;
            }

            return $this->matcher = new $matcher($cacheFile);
        }

        return $this->matcher = new $matcher($this->getCollection());
    }
}
