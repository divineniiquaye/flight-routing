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

namespace Flight\Routing\Traits;

use Flight\Routing\Interfaces\RouteInterface;
use Flight\Routing\Route;

trait RouteTrait
{
    /** @var string[] */
    private $methods = [];

    /** @var string */
    private $path;

    /** @var null|string */
    private $domain;

    /** @var string */
    private $name;

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
     * {@inheritdoc}
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * {@inheritdoc}
     */
    public function addPrefix(string $prefix): RouteInterface
    {
        $this->path = $this->castPrefix($this->path, $prefix);

        return $this;
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
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function setName(string $name): RouteInterface
    {
        $this->name = $name;

        return $this;
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
    public function setDomain(string $domain): RouteInterface
    {
        \preg_match(Route::URL_PATTERN, $domain, $matches);

        if (isset($matches['scheme']) && !empty($scheme = $matches['scheme'])) {
            $this->setScheme($scheme);
        }

        $this->domain = \ltrim($matches['domain'] ?? $domain, '//');

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getSchemes(): array
    {
        return $this->schemes;
    }

    /**
     * {@inheritdoc}
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
    public function getArguments(): array
    {
        $arguments = [];

        foreach ($this->arguments as $key => $value) {
            if (\is_int($key)) {
                continue;
            }
            $value = \is_numeric($value) ? (int) $value : $value;

            $arguments[$key] = \is_string($value) ? \rawurldecode($value) : $value;
        }

        return $arguments;
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
    public function getDefaults(): array
    {
        return $this->defaults;
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
    public function addPattern(string $name, $expression): RouteInterface
    {
        $this->patterns[$name] = $expression;

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
    public function setPatterns(array $patterns): RouteInterface
    {
        foreach ($patterns as $key => $expression) {
            $this->addPattern($key, $expression);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    /**
     * {@inheritdoc}
     */
    public function addMiddleware(...$middlewares): RouteInterface
    {
        /** @var int|string $index */
        foreach ($middlewares as $index => $middleware) {
            if (!\is_callable($middleware) && (\is_int($index) && \is_array($middleware))) {
                $this->addMiddleware(...$middleware);

                continue;
            }

            $this->middlewares[] = $middleware;
        }

        return $this;
    }
}
