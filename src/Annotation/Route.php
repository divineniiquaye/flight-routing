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

namespace Flight\Routing\Annotation;

use Flight\Routing\Exceptions\InvalidAnnotationException;

/**
 * Annotation class for @Route().
 *
 * @Annotation
 *
 * ```php
 * <?php
 *  /**
 *   * @Route("/blog" name="blog", methods={"GET", "HEAD"}, defaults={"_locale" = "en"})
 *   * /
 *  class Blog
 *  {
 *     /**
 *      * @Route("/{_locale}", name="index", methods={"GET", "HEAD"})
 *      * /
 *     public function index()
 *     {
 *     }
 *     /**
 *      * @Route("/{_locale}/{id}", name="post", methods={"GET", "HEAD"}, patterns={"id" = "[0-9]+"})
 *      * /
 *     public function show($id)
 *     {
 *     }
 *  }
 * ```
 *
 * @Target({"CLASS", "METHOD"})
 */
final class Route
{
    /** @var string @Required */
    private $path;

    /** @var string @Required */
    private $name;

    /** @var string[] @Required */
    private $methods;

    /** @var null|string */
    private $domain;

    /** @var string[] */
    private $schemes;

    /** @var string[] */
    private $middlewares;

    /** @var array<string,string> */
    private $patterns;

    /** @var array<string,mixed> */
    private $defaults;

    /**
     * @param array<string,mixed> $params
     */
    public function __construct(array $params)
    {
        if (isset($params['value'])) {
            $params['path'] = $params['value'];
            unset($params['value']);
        }

        $params = array_merge([
            'middlewares' => [],
            'patterns' => [],
            'defaults' => [],
            'schemes' => [],
            'domain' => null,
        ], $params);

        $this->assertParamsContainValidName($params);
        $this->assertParamsContainValidPath($params);
        $this->assertParamsContainValidMethods($params);
        $this->assertParamsContainValidSchemes($params);
        $this->assertParamsContainValidMiddlewares($params);
        $this->assertParamsContainValidPatterns($params);
        $this->assertParamsContainValidDefaults($params);

        $this->name        = $params['name'];
        $this->path        = $params['path'];
        $this->methods     = $params['methods'];
        $this->domain      = $params['domain'];
        $this->middlewares = $params['middlewares'];
        $this->schemes     = $params['schemes'];
        $this->patterns    = $params['patterns'];
        $this->defaults    = $params['defaults'];
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getDomain(): ?string
    {
        return $this->domain;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPatterns(): array
    {
        return $this->patterns;
    }

    public function getMethods(): array
    {
        return $this->methods;
    }

    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    public function getSchemes(): array
    {
        return $this->schemes;
    }

    public function getDefaults(): array
    {
        return $this->defaults;
    }

    /**
     * @param array $params
     *
     * @throws InvalidAnnotationException
     */
    private function assertParamsContainValidName(array $params): void
    {
        if (empty($params['name']) || !\is_string($params['name'])) {
            throw new InvalidAnnotationException('@Route.name must be not an empty string.');
        }
    }

    /**
     * @param array $params
     *
     * @throws InvalidAnnotationException
     */
    private function assertParamsContainValidPath(array $params): void
    {
        if (empty($params['path']) || !\is_string($params['path'])) {
            throw new InvalidAnnotationException('@Route.path must be not an empty string.');
        }
    }

    /**
     * @param array $params
     *
     * @throws InvalidAnnotationException
     */
    private function assertParamsContainValidMethods(array $params): void
    {
        if (empty($params['methods']) || !\is_array($params['methods'])) {
            throw new InvalidAnnotationException('@Route.methods must be not an empty array.');
        }

        foreach ($params['methods'] as $method) {
            if (!\is_string($method)) {
                throw new InvalidAnnotationException('@Route.methods must contain only strings.');
            }
        }
    }

    /**
     * @param array $params
     *
     * @throws InvalidAnnotationException
     */
    private function assertParamsContainValidMiddlewares(array $params): void
    {
        if (!\is_array($params['middlewares'])) {
            throw new InvalidAnnotationException('@Route.middlewares must be an array.');
        }

        foreach ($params['middlewares'] as $middleware) {
            if (!\is_string($middleware)) {
                throw new InvalidAnnotationException('@Route.middlewares must contain only strings.');
            }
        }
    }

    /**
     * @param array $params
     *
     * @throws InvalidAnnotationException
     */
    private function assertParamsContainValidPatterns(array $params): void
    {
        if (!\is_array($params['patterns'])) {
            throw new InvalidAnnotationException('@Route.patterns must be an array.');
        }

        foreach ($params['patterns'] as $pattern) {
            if (!\is_string($pattern)) {
                throw new InvalidAnnotationException('@Route.patterns must contain only strings.');
            }
        }
    }

    /**
     * @param array $params
     *
     * @throws InvalidAnnotationException
     */
    private function assertParamsContainValidSchemes(array $params): void
    {
        if (!\is_array($params['schemes'])) {
            throw new InvalidAnnotationException('@Route.schemes must be an array.');
        }

        foreach ($params['schemes'] as $scheme) {
            if (!\is_string($scheme)) {
                throw new InvalidAnnotationException('@Route.schemes must contain only strings.');
            }
        }
    }

    /**
     * @param array $params
     *
     * @throws InvalidAnnotationException
     */
    private function assertParamsContainValidDefaults(array $params): void
    {
        if (!\is_array($params['defaults'])) {
            throw new InvalidAnnotationException('@Route.defaults must be an array.');
        }
    }
}
