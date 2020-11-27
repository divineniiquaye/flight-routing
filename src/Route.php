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

use Flight\Routing\Interfaces\RouteInterface;

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
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class Route implements RouteInterface
{
    use Traits\CastingTrait;

    /**
     * A Pattern to Locates appropriate route by name, support dynamic route allocation using following pattern:
     * Pattern route:   `pattern/*<controller@action>`
     * Default route: `*<controller@action>`
     * Only action:   `pattern/*<action>`.
     *
     * @var string
     */
    public const RCA_PATTERN = '/^(?:(?P<route>[^(.*)]+)\*<)?(?:(?P<controller>[^@]+)@+)?(?P<action>[a-z_\-]+)\>$/i';

    /** @var string[] */
    private $methods = [];

    /** @var string */
    private $path;

    /** @var null|string */
    private $domain;

    /** @var string */
    private $name;

    /** @var callable|object|string|string[] */
    private $controller;

    /** @var string[] */
    private $schemes = [];

    /** @var array<int|string,mixed> */
    private $arguments = [];

    /** @var array<string,mixed> */
    private $defaults = [];

    /** @var array<string,string|string[]> */
    private $patterns = [];

    /** @var array<int,mixed> */
    private $middlewares = [];

    /**
     * Create a new Route constructor.
     *
     * @param string                               $name    The route name
     * @param string[]                             $methods The route HTTP methods
     * @param string                               $pattern The route pattern
     * @param null|callable|object|string|string[] $handler The route callable
     */
    public function __construct(string $name, array $methods, string $pattern, $handler)
    {
        $this->name       = $name;
        $this->controller = null === $handler ? '' : $handler;
        $this->methods    = \array_map('strtoupper', $methods);
        $this->path       = $this->castRoute($pattern);
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * {@inheritDoc}
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * {@inheritDoc}
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    /**
     * {@inheritdoc}
     */
    public function getDomain(): string
    {
        return \str_replace(['http://', 'https://'], '', (string) $this->domain);
    }

    /**
     * {@inheritdoc}
     */
    public function getSchemes(): array
    {
        return $this->schemes;
    }

    /**
     * {@inheritDoc}
     */
    public function getController()
    {
        return $this->controller;
    }

    /**
     * {@inheritdoc}
     */
    public function getArguments(): array
    {
        $arguments = [];

        foreach ($this->arguments as $key => $value) {
            if (\is_int($key)) {
                continue;
            }

            $value           = \is_numeric($value) ? (int) $value : $value;
            $arguments[$key] = \is_string($value) ? \rawurldecode($value) : $value;
        }

        return $arguments;
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
    public function getPatterns(): array
    {
        return $this->patterns;
    }

    /**
     * {@inheritdoc}
     */
    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    /**
     * {@inheritDoc}
     */
    public function setName(string $name): RouteInterface
    {
        $this->name = $name;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setDomain(string $domain): RouteInterface
    {
        if (false !== \preg_match('@^(?:(https?):)?(\/\/[^/]+)@i', $domain, $matches)) {
            if (empty($matches)) {
                $matches = [$domain, null, $domain];
            }

            [, $scheme, $domain] = $matches;

            if (!empty($scheme)) {
                $this->setScheme($scheme);
            }
        }
        $this->domain = \trim((string) $domain, '//');

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function setScheme(string ...$schemes): RouteInterface
    {
        foreach ($schemes as $scheme) {
            $this->schemes[] = $scheme;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setArguments(array $arguments): RouteInterface
    {
        foreach ($arguments as $key => $value) {
            $this->arguments[$key] = $value;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaults(array $defaults): RouteInterface
    {
        foreach ($defaults as $key => $value) {
            $this->defaults[$key] = $value;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setPatterns(array $patterns): RouteInterface
    {
        foreach ($patterns as $key => $expression) {
            $this->addPattern($key, $expression);
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function addMethod(string ...$methods): RouteInterface
    {
        foreach ($methods as $method) {
            $this->methods[] = \strtoupper($method);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addPattern(string $name, $expression): RouteInterface
    {
        $this->patterns[$name] = $expression;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function addPrefix(string $prefix): RouteInterface
    {
        $this->path = $this->castPrefix($this->path, $prefix);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function addMiddleware(...$middlewares): RouteInterface
    {
        foreach ($middlewares as $middleware) {
            $this->middlewares[] = $middleware;
        }

        return $this;
    }
}
