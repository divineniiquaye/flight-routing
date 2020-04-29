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

use BiuradPHP\Support\BoundMethod;
use Flight\Routing\Interfaces\CallableResolverInterface;
use Flight\Routing\Interfaces\RouteGroupInterface;
use Flight\Routing\Interfaces\RouteInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function array_key_exists;
use function is_numeric;
use function str_replace;
use function is_array;
use function class_exists;
use function array_merge;
use function sprintf;
use function in_array;
use function rtrim;
use function ltrim;
use function mb_strpos;
use function is_int;
use function is_countable;

/**
 * Value object representing a single route.
 *
 * Routes are a combination of path, middleware, and HTTP methods; two routes
 * representing the same path and overlapping HTTP methods are not allowed,
 * while two routes representing the same path and non-overlapping HTTP methods
 * can be used (and should typically resolve to different middleware).
 *
 * Internally, only those three properties are required. However, underlying
 * router implementations may allow or require additional information, such as
 * information defining how to generate a URL from the given route, qualifiers
 * for how segments of a route match, or even default values to use. These may
 * be provided after instantiation via the "defaults" property and related
 * addDefaults() method.
 */
class Route implements RouteInterface, RequestHandlerInterface
{
    /**
     * HTTP methods supported by this route
     *
     * @var string[]
     */
    protected $methods = [];

    /**
     * Route name
     *
     * @var null|string
     */
    protected $name;

    /**
     * Route parameters
     *
     * @var array
     */
    protected $arguments = [];

    /**
     * Route Middlewares
     *
     * @var array
     */
    protected $middlewares = [];

    /**
     * Route arguments parameters
     *
     * @var array
     */
    protected $savedArguments = [];

    /**
     * Parent route groups
     *
     * @var RouteGroupInterface[]
     */
    protected $groups;

    /**
     * @var ResponseInterface
     */
    protected $response;

    /**
     * Container
     *
     * @var ContainerInterface|null
     */
    protected $container;

    /**
     * @var RouteMiddleware
     */
    protected $middlewareDispatcher;

    /**
     * Route callable
     *
     * @var callable|string
     */
    protected $controller;

    /**
     * @var CallableResolverInterface
     */
    protected $callableResolver;

    /**
     * Route path pattern
     *
     * @var string
     */
    protected $path;

    /**
     * Route domain
     *
     * @var string
     */
    protected $domain = '';

    /**
     * Route Namespace
     *
     * @var string
     */
    protected $namespace;

    /**
     * Route Patterns
     *
     * @var array
     */
    protected $patterns = [];

    /**
     * Route defaults
     *
     * @var array
     */
    protected $defaults = [];

    /**
     * @var bool
     */
    protected $groupAppended = false;

    /**
     * Create a new Route constructor.
     *
     * @param string[]                         $methods    The route HTTP methods
     * @param string                           $pattern    The route pattern
     * @param callable|string                  $callable   The route callable
     * @param RouteMiddleware                  $middleware
     * @param ResponseInterface                $response
     * @param CallableResolverInterface        $callableResolver
     * @param ContainerInterface|null          $container
     * @param RouteGroup[]                     $groups     The parent route groups
     */
    public function __construct(
        array $methods, string $pattern, $callable,
        CallableResolverInterface $callableResolver,
        RouteMiddleware $middleware, ResponseInterface $response,
        ContainerInterface $container = null, array $groups = []
    ) {
        $this->methods = $methods;
        $this->appendGroupToRoute($groups);
        $this->setPath($pattern);
        $this->setController($callable);

        $this->response             = $response;
        $this->callableResolver     = $callableResolver;
        $this->container            = $container;
        $this->middlewareDispatcher = $middleware;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'path'          => $this->getPath(),
            'name'          => $this->getName(),
            'methods'       => $this->getMethods(),
            'domain'        => $this->getDomain(),
            'controller'    => $this->container,
            'middlewares'   => $this->middlewares,
            'defaults'      => $this->getDefaults(),
            'patterns'      => $this->getPatterns(),
            'arguments'     => $this->getArguments(),
        ];
    }

    /**
     * @param array $values
     *
     * @throws RuntimeException
     */
    public function fromArray(array $values): void
    {
        try {
            foreach ($values as $key => $value) {
                if (null !== $value) {
                    if ('defaults' === $key) {
                        $this->addDefaults($value);
                    } elseif ('patterns' === $key) {
                        $this->whereArray($value);
                    } else {
                        $this->$key = $this->getValueFromKey($values, $key);
                    }
                }
            }
        } catch (\Throwable $exception) {
            throw new \RuntimeException($exception->getMessage());
        }
    }

    /**
     * Ensures that the right-most slash is trimmed for prefixes of more than
     * one character, and that the prefix begins with a slash.
     *
     * @param string $uri
     * @param mixed $prefix
     */
    private function normalizePrefix(string $uri, $prefix)
    {
        // Allow homepage uri on prefix just like python dgango url style.
        if (in_array($uri, ['', '/'])) {
            return rtrim($prefix, '/') . $uri;
        }

        $urls = [];
        foreach (['&', '-', '_', '~', '@'] as $symbols) {
            if (mb_strpos($prefix, $symbols) !== false) {
                $urls[] = rtrim($prefix, '/') . $uri;
            }
        }

        return $urls ? $urls[0] : rtrim($prefix, '/') . '/' . ltrim($uri, '/');
    }

    /**
     * Sets the pattern for the path.
     *
     * @param string $pattern The path pattern
     *
     * @return $this
     */
    protected function setPath(string $pattern): void
    {
        if (isset($this->groups[RouteGroupInterface::PREFIX])) {
            $pattern = $this->normalizePrefix($pattern, $this->groups[RouteGroupInterface::PREFIX]);
        }

        $this->path = (empty($pattern) || '/' === $pattern) ? '/' : $pattern;
    }

    /**
     * @return callable|string
     */
    protected function setController($controller): void
    {
        $namespace = $this->groups[RouteGroupInterface::NAMESPACE] ?? $this->namespace;

        if (
            is_string($controller) &&
            null !== $namespace &&
            false === mb_strpos($controller, $namespace)
        ) {
            $controller = $namespace . $controller;
        } elseif (
            is_array($controller) && !$controller instanceof \Closure &&
            !is_object($controller[0]) && !class_exists($controller[0]) && count($controller) === 2)
        {
            $controller[0] = $namespace . $controller[0];
        }

        $this->controller = $controller;
    }

    /**
     * @return void
     */
    protected function appendGroupToRoute(array $groups): void
    {
        if (empty($groups)) return;
        $this->groups = $groups[0]->getOptions();

        if (isset($this->groups[RouteGroupInterface::MIDDLEWARE])) {
            $this->middlewares = array_merge($this->groups[RouteGroupInterface::MIDDLEWARE], $this->middlewares);
        }

        if (isset($this->groups[RouteGroupInterface::DOMAIN])) {
            $this->setDomain($this->groups[RouteGroupInterface::DOMAIN]);
        }

        $this->groupAppended = true;
    }

    /**
     * {@inheritdoc}
     */
    public function hasGroup(): bool
    {
        return $this->groupAppended;
    }

    /**
     * {@inheritdoc}
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * {@inheritdoc}
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    /**
     * {@inheritdoc}
     */
    public function setDomain(?string $domain = null): RouteInterface
    {
        if (null !== $domain) {
            $this->domain = $domain;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getDomain(): string
    {
        return str_replace(['http://', 'https://'], '', $this->domain);
    }

    /**
     * {@inheritdoc}
     */
    public function setArgument(string $name, ?string $value, bool $includeInSavedArguments = true): RouteInterface
    {
        // Resolving the value with numbers.
        $value = (null !== $value && is_numeric($value)) ? (int) $value : $value;

        if ($includeInSavedArguments) {
            $this->savedArguments[$name] = $value;
        }

        if (null !== $value) {
            $this->arguments[$name] = $value;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getArgument(string $name, ?string $default = null): ?string
    {
        if (array_key_exists($name, $this->arguments)) {
            return $this->arguments[$name];
        }

        return $default;
    }

    /**
     * {@inheritdoc}
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * {@inheritdoc}
     */
    public function setArguments(array $arguments, bool $includeInSavedArguments = true): RouteInterface
    {
        if ($includeInSavedArguments) {
            $this->savedArguments = $arguments;
        }

        $this->arguments = $arguments;

        return $this;
    }

    /**
     * Add or change the route name.
     *
     * @param string $name
     *
     * @return $this
     */
    public function setName(?string $name): RouteInterface
    {
        $current = isset($this->groups[RouteGroupInterface::NAME])
            ? $this->groups[RouteGroupInterface::NAME] : null;
        $definedName = null !== $current ? $current . $name : $name;

        if (null !== $current && mb_strpos($current, '.') === false) {
            $definedName = sprintf('%s.%s', $current, $name);
        }

        $this->name = $definedName;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function setPattern(string $name, string $expression = null): RouteInterface
    {
        $this->patterns = array_merge($this->parseWhere($name, $expression), $this->patterns);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getPatterns(): array
    {
        return $this->patterns;
    }

    /**
     * {@inheritdoc}
     */
    public function addDefaults(array $defaults): RouteInterface
    {
        foreach ($defaults as $name => $default) {
            $this->defaults[$name] = $default;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefault(string $name, ?string $default = null): ?string
    {
        if (array_key_exists($name, $this->defaults)) {
            return $this->defaults[$name];
        }

        return $default;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaults(): array
    {
        return $this->defaults;
    }

    /**
     * {@inheritdoc}
     */
    public function hasDefault($name)
    {
        return \array_key_exists($name, $this->defaults);
    }

    /**
     * {@inheritdoc}
     */
    public function whereArray(array $wheres = []): RouteInterface
    {
        foreach ($wheres as $name => $expression) {
            $this->setPattern($name, $expression);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addMiddleware($middleware): RouteInterface
    {
        $this->middlewares = array_merge(
            is_array($middleware) ? $middleware : [$middleware], $this->middlewares
        );

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function prepare(array $arguments): RouteInterface
    {
        // Remove temp arguments
        $this->setArguments($this->savedArguments);

        // Add the arguments
        foreach ($arguments as $k => $v) {
            if (is_int($k)) continue;

            $this->setArgument($k, $v);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function run(ServerRequestInterface $request): ResponseInterface
    {
        // Get all available middlewares
        $middlewares = array_merge($this->middlewares, $this->middlewareDispatcher->getMiddlewareStack());

        // Allow Middlewares to be disabled
        if (in_array('off', $middlewares) || in_array('disable', $middlewares)) {
            $middlewares = [];
        }

        if (count($middlewares) > 0) {
            // This middleware is in the priority map. If we have encountered another middleware
            // that was also in the priority map and was at a lower priority than the current
            // middleware, we will move this middleware to be above the previous encounter.
            $middleware = $this->middlewareDispatcher->pipeline($middlewares);

            try {
                $requestHandler = $this->middlewareDispatcher->addhandler($this);
            } finally {
                // This middleware is in the priority map; but, this is the first middleware we have
                // encountered from the map thus far. We'll save its current index plus its index
                // from the priority map so we can compare against them on the next iterations.
                return $middleware->process($request, $requestHandler);
            };
        }

        return $this->handle($request);
    }

    /**
     * {@inheritdoc}
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $callable = $this->callableResolver->resolve($this->controller);
        $request = $request->withAttribute('arguments', $this->arguments);
        $response = null; // An empty response type.

        // If controller is instance of RequestHandlerInterface
        if (
            is_countable($callable) && count($callable) === 2 &&
            $callable[0] instanceof RequestHandlerInterface
        ) {
            return $callable($request);
        }

        if (class_exists(BoundMethod::class)) {
            $response = BoundMethod::call($this->container, $callable, $this->arguments + [$request]);
        }

        return $this->callableResolver->returnType(
            $response ?? $callable($request, $this->response, $this->arguments),
            $this->response
        );
    }

    /**
     * @param array $data
     * @param string $key
     * @param string|null $message
     *
     * @return mixed
     *
     */
    private function getValueFromKey(array $data, string $key, string $message = null)
    {
        if (isset($data[$key])) {
            return $data[$key];
        }

        if (null === $message) {
            $message = sprintf('Missing "%s" key in route collection', $key);
        }

        throw new \RuntimeException($message);
    }

    /**
     * Parse arguments to the where method into an array.
     *
     * @param array|string $name
     * @param string       $expression
     *
     * @return array
     */
    private function parseWhere($name, $expression): array
    {
        return is_array($name) ? $name : [$name => $expression];
    }
}
