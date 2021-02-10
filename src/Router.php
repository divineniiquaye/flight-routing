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
use Flight\Routing\Interfaces\MatcherDumperInterface;
use Flight\Routing\Interfaces\RouteMatcherInterface;
use Flight\Routing\Interfaces\RouterInterface;
use Laminas\Stratigility\MiddlewarePipe;
use Laminas\Stratigility\MiddlewarePipeInterface;
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

    /**
     * @param array<string,mixed> $options
     */
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
        $this->routes   = new RouteCollection(false);
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
     *   * options_skip:           Whether to serve a response on HTTP request OPTIONS method (false by default)
     *
     * @throws \InvalidArgumentException When unsupported option is provided
     */
    public function setOptions(array $options): void
    {
        $this->options = [
            'cache_dir'            => null,
            'debug'                => false,
            'options_skip'         => false,
            'namespace'            => null,
            'matcher_class'        => Matchers\SimpleRouteMatcher::class,
            'matcher_dumper_class' => Matchers\SimpleRouteDumper::class,
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

        if ($this->options['debug']) {
            $this->debug = new DebugRoute();
        }

        // Set the cache_file for caching compiled routes.
        if (isset($this->options['cache_dir'])) {
            $this->options['cache_file'] = $this->options['cache_dir'] . '/compiled_routes.php';
        }
    }

    /**
     * This is true if debug mode is false and cached routes exists.
     */
    public function isFrozen(): bool
    {
        if ($this->options['debug']) {
            return false;
        }

        return \file_exists($this->options['cache_file'] ?? '');
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
            if (null === $name = $route->get('name')) {
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
        $createUri = (string) $this->getMatcher()->generateUri($routeName, $parameters, $queryParams);

        return $this->uriFactory->createUri($createUri);
    }

    /**
     * Looks for a route that matches the given request
     *
     * @throws MethodNotAllowedException
     * @throws UriHandlerException
     * @throws RouteNotFoundException
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

        if ($this->options['debug']) {
            $this->debug->setMatched(new DebugRoute($route->get('name'), $route));
        }

        return $this->route = clone $route;
    }

    /**
     * {@inheritDoc}
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // This is to aid request made from javascript using cors, eg: using axios.
        // Midddlware support is added, so it make it easier to add "cors" settings to the response and request
        if ($this->options['options_skip'] && \strtolower($request->getMethod()) === 'options') {
            return $this->handleOptionsResponse($request);
        }

        $route   = $this->match($request);
        $handler = $this->route->getController();

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
            $this->addMiddleware(...$this->resolveMiddlewares($route));

            return $this->pipeline->process($request->withAttribute(Route::class, $route), $handler);
        } finally {
            if ($this->options['debug']) {
                foreach ($this->debug->getProfiles() as $profiler) {
                    $profiler->leave();
                }
            }
        }
    }

    /**
     * Gets the RouteMatcherInterface instance associated with this Router.
     */
    public function getMatcher(): RouteMatcherInterface
    {
        if (null !== $this->matcher) {
            return $this->matcher;
        } elseif ($this->isFrozen()) {
            return $this->matcher = $this->getDumper($this->options['cache_file']);
        }

        if (!$this->options['debug'] && isset($this->options['cache_file'])) {
            $dumper = $this->getDumper($this->routes);

            if ($dumper instanceof MatcherDumperInterface) {
                $cacheDir = $this->options['cache_dir'];
                $cacheFile = $this->options['cache_file'];

                if (!\file_exists($cacheDir)) {
                    @\mkdir($cacheDir, 0777, true);
                }

                \file_put_contents($cacheFile, $dumper->dump());

                if (
                    \function_exists('opcache_invalidate') &&
                    \filter_var(\ini_get('opcache.enable'), \FILTER_VALIDATE_BOOLEAN)
                ) {
                    @opcache_invalidate($cacheFile, true);
                }
            }

            return $this->matcher = $dumper;
        }

        /** @var RouteMatcherInterface $matcher */
        $matcher = new $this->options['matcher_class']($this->routes);

        return $this->matcher = $matcher;
    }

    /**
     * @param RouteCollection|string $routes
     */
    private function getDumper($routes): RouteMatcherInterface
    {
        return new $this->options['matcher_dumper_class']($routes);
    }

    /**
     * We have allowed middleware from router to run on response due to
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    private function handleOptionsResponse(ServerRequestInterface $request): ResponseInterface
    {
        return $this->pipeline->process(
            $request,
            new Handlers\CallbackHandler(
                function (ServerRequestInterface $request): ResponseInterface {
                    return $this->responseFactory->createResponse();
                }
            )
        );
    }
}
