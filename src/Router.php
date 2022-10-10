<?php

declare(strict_types=1);

/*
 * This file is part of Flight Routing.
 *
 * PHP version 7.4 and above required
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 Biurad Group (https://biurad.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flight\Routing;

use Fig\Http\Message\RequestMethodInterface;
use Flight\Routing\Exceptions\UrlGenerationException;
use Flight\Routing\Interfaces\{RouteCompilerInterface, RouteMatcherInterface, UrlGeneratorInterface};
use Laminas\Stratigility\Next;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface, UriInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};

/**
 * Aggregate routes for matching and Dispatching.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class Router implements RouteMatcherInterface, RequestMethodInterface, MiddlewareInterface, UrlGeneratorInterface
{
    use Traits\CacheTrait, Traits\ResolverTrait;

    /** @var array<int,string> Default methods for route. */
    public const DEFAULT_METHODS = [self::METHOD_GET, self::METHOD_HEAD];

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

    private RouteCompilerInterface $compiler;
    private ?\SplQueue $pipeline = null;
    private \Closure|RouteCollection|null $collection = null;

    /** @var array<string,array<int,MiddlewareInterface>> */
    private array $middlewares = [];

    /**
     * @param null|string $cache file path to store compiled routes
     */
    public function __construct(RouteCompilerInterface $compiler = null, string $cache = null)
    {
        $this->cache = $cache;
        $this->compiler = $compiler ?? new RouteCompiler();
    }

    /**
     * Set a route collection instance into Router in order to use addRoute method.
     *
     * @param null|string $cache file path to store compiled routes
     */
    public static function withCollection(
        \Closure|RouteCollection $collection = null,
        RouteCompilerInterface $compiler = null,
        string $cache = null
    ): static {
        $new = new static($compiler, $cache);
        $new->collection = $collection;

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function match(string $method, UriInterface $uri): ?array
    {
        return $this->optimized[$method.$uri->__toString()] ??= [$this, $this->cache ? 'resolveCache' : 'resolveRoute'](
            \rtrim(\rawurldecode($uri->getPath()), '/') ?: '/',
            $method,
            $uri
        );
    }

    /**
     * {@inheritdoc}
     */
    public function matchRequest(ServerRequestInterface $request): ?array
    {
        $requestUri = $request->getUri();
        $pathInfo = $request->getServerParams()['PATH_INFO'] ?? '';

        // Resolve request path to match sub-directory or /index.php/path
        if ('' !== $pathInfo && $pathInfo !== $requestUri->getPath()) {
            $requestUri = $requestUri->withPath($pathInfo);
        }

        return $this->match($request->getMethod(), $requestUri);
    }

    /**
     * {@inheritdoc}
     */
    public function generateUri(string $routeName, array $parameters = [], int $referenceType = RouteUri::ABSOLUTE_PATH): RouteUri
    {
        if (empty($matchedRoute = &$this->optimized[$routeName] ?? null)) {
            foreach ($this->getCollection()->getRoutes() as $route) {
                if (isset($route['name']) && $route['name'] === $routeName) {
                    $matchedRoute = $route;
                    break;
                }
            }
        }

        if (!isset($matchedRoute)) {
            throw new UrlGenerationException(\sprintf('Route "%s" does not exist.', $routeName));
        }

        return $this->compiler->generateUri($matchedRoute, $parameters, $referenceType)
            ?? throw new UrlGenerationException(\sprintf('%s::generateUri() not implemented in compiler.', $this->compiler::class));
    }

    /**
     * Attach middleware to the pipeline.
     */
    public function pipe(MiddlewareInterface ...$middlewares): void
    {
        if (null === $this->pipeline) {
            $this->pipeline = new \SplQueue();
        }

        foreach ($middlewares as $middleware) {
            $this->pipeline->enqueue($middleware);
        }
    }

    /**
     * Attach a name to a group of middlewares.
     */
    public function pipes(string $name, MiddlewareInterface ...$middlewares): void
    {
        $this->middlewares[$name] = $middlewares;
    }

    /**
     * Sets the RouteCollection instance associated with this Router.
     *
     * @param (callable(RouteCollection): void) $routeDefinitionCallback takes only one parameter of route collection
     */
    public function setCollection(callable $routeDefinitionCallback): void
    {
        $this->collection = $routeDefinitionCallback;
    }

    /**
     *  Get the RouteCollection instance associated with this Router.
     */
    public function getCollection(): RouteCollection
    {
        if ($this->cache) {
            return $this->optimized[2] ?? $this->doCache();
        }

        if ($this->collection instanceof \Closure) {
            ($this->collection)($this->collection = new RouteCollection());
        }

        return $this->collection ??= new RouteCollection();
    }

    /**
     * Set a route compiler instance into Router.
     */
    public function setCompiler(RouteCompiler $compiler): void
    {
        $this->compiler = $compiler;
    }

    public function getCompiler(): RouteCompilerInterface
    {
        return $this->compiler;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $route = $this->matchRequest($request);

        if (null !== $route) {
            foreach ($route['middlewares'] ?? [] as $a => $b) {
                if (isset($this->middlewares[$a])) {
                    $this->pipe(...$this->middlewares[$a]);
                }
            }
        }

        if (!empty($this->pipeline)) {
            $handler = new Next($this->pipeline, $handler);
        }

        return $handler->handle($request->withAttribute(self::class, $route));
    }
}
