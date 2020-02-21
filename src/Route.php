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

use Throwable, RuntimeException;
use Flight\Routing\Concerns\HttpMethods;

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
 * be provided after instantiation via the "options" property and related
 * setOptions() method.
 */
class Route
{
    use Concerns\Registrar;

    /** @var bool|null */
    private $disabledMiddleware = false;

    /**
     * Create a new Route constructor.
     *
     * @param RouteCollector $router
     * @param array|string   $methods
     * @param string         $uri
     * @param \Closure|array $action
     */
    public function __construct(RouteCollector $router, array $methods, string $uri, $action)
    {
        // Resolve method to array
        $method = is_array($methods) ? $methods : [$methods];

        $this->router = $router;
        $this->setPath($uri);
        $this->method = $method;
        $this->controller = $action;

        if (HttpMethods::METHOD_GET == $this->method && !in_array('HEAD', $this->method, true)) {
            $this->method[] = HttpMethods::METHOD_HEAD;
        }
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'uri' => $this->getUri(),
            'name' => $this->getName(),
            'method' => $this->getMethod(),
            'domain' => $this->getDomain(),
            'controller' => $this->getController(),
            'middleware' => $this->getMiddleware(),
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
                    if (property_exists($this, $key)) {
                        $this->$key = $this->getValueFromKey($values, $key);
                    }
                }
            }
        } catch (Throwable $exception) {
            throw new RuntimeException($exception->getMessage());
        }
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

        throw new RuntimeException($message);
    }

    /**
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->toJson();
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

    /**
     * Ensures that the right-most slash is trimmed for prefixes of more than
     * one character, and that the prefix begins with a slash.
     *
     * @param string $uri
     * @param mixed $prefix
     */
    private function normalizePrefix(string $uri, $prefix)
    {
        $urls = [];
        foreach (['&', '-', '_', '~', '@'] as $symbols) {
            if (mb_strpos($prefix, $symbols) !== false) {
                $urls[] = rtrim($prefix, '/') . $uri;
            }
        }

        return $urls ? $urls[0] : rtrim($prefix, '/') . '/' . ltrim($uri, '/');
    }

    /**
     * Get or set the domain for the route.
     *
     * @param string|null $domain
     *
     * @return $this|string|null
     */
    public function domain(?string $domain = null)
    {
        if (null === $domain) {
            return $this->getDomain();
        }

        $this->domain = $domain;

        return $this;
    }

    /**
     * Add or change the route name.
     *
     * @param string $name
     *
     * @return $this
     */
    public function name(?string $name): self
    {
        $current = $this->router->getCurrentName();
        $definedName = isset($current) ? $current . $name : $name;

        if (isset($current) && mb_strpos($current, '.') === false) {
            $definedName = sprintf('%s.%s', $current, $name);
        }

        $this->name = $definedName;

        return $this;
    }

    /**
     * Get or set the middlewares attached to the route.
     *
     * @param array|string|null $middleware
     *
     * @return $this|array
     */
    public function middlewares(array $middleware = []): self
    {
        $current = $this->router->getCurrentMiddleware();
        $middleware = array_diff($middleware, $current);

        $this->middleware = array_merge($this->middleware, $middleware);

        return $this;
    }

    /**
     * Set a regular expression requirement on the route.
     *
     * @param array|string $name
     * @param string       $expression
     *
     * @return $this
     */
    public function define(string $name, string $expression = null): self
    {
        $this->router->getCollection()
            ->addParameters($this->parseWhere($name, $expression));

        return $this;
    }

    /**
     * @param string|null $name
     * @return $this
     */
    public function prefix(?string $name = null): self
    {
        $uri = $this->uri;

        if ($prefix = $name) {
            $uri = $this->normalizePrefix($uri, $prefix);
        }

        $this->uri = $uri;

        return $this;
    }

    /**
     * Get the status of middlewares.
     */
    public function disabledMiddlewares(): ?bool
    {
        return $this->disabledMiddleware;
    }

    /**
     * Disable middlewares on route
     */
    public function disableMiddlewares(bool $status = true)
    {
        $this->disabledMiddleware = $status;

        return $this;
    }

    /**
     * Set a list of regular expression requirements on the route.
     *
     * @param array $wheres
     *
     * @return $this
     */
    public function whereArray(array $wheres = []): self
    {
        foreach ($wheres as $name => $expression) {
            $this->define($name, $expression);
        }

        return $this;
    }
}
