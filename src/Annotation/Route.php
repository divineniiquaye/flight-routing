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
 * On PHP 8, the annotation class can be used as an attribute as well:
 * ```php
 *     #[Route('/Blog', methods: ['GET', 'POST'])]
 *     class Blog
 *     {
 *         #[Route('/', name: 'blog_index')]
 *         public function index()
 *         {
 *         }
 *         #[Route('/{id}', name: 'blog_post', patterns: ["id" => '\d+'])]
 *         public function show()
 *         {
 *         }
 *     }
 * ```
 *
 * @Target({"CLASS", "METHOD"})
 */
#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final class Route
{
    /** @var string @Required */
    private $path;

    /** @var null|string @Required */
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
     * @param array<string,mixed>|string $params      data array managed by the Doctrine Annotations library or the path
     * @param null|string                $path
     * @param string                     $name
     * @param string[]                   $methods
     * @param string[]                   $patterns
     * @param string[]                   $defaults
     * @param string                     $domain
     * @param string[]                   $schemes
     * @param string[]                   $middlewares
     */
    public function __construct(
        $params = [],
        ?string $path = null,
        string $name = null,
        array $methods = [],
        array $patterns = [],
        array $defaults = [],
        string $domain = null,
        array $schemes = [],
        array $middlewares = []
    ) {
        if (is_array($params) && isset($params['value'])) {
            $params['path'] = $params['value'];
            unset($params['value']);
        } elseif (\is_string($params)) {
            $params = ['path' => $params];
        }

        $params = \array_merge([
            'middlewares' => $middlewares,
            'patterns'    => $patterns,
            'defaults'    => $defaults,
            'schemes'     => $schemes,
            'methods'     => $methods,
            'domain'      => $domain,
            'name'        => $name,
            'path'        => $path,
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

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return null|string
     */
    public function getDomain(): ?string
    {
        return $this->domain;
    }

    /**
     * @return null|string
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @return array<string,string>
     */
    public function getPatterns(): array
    {
        return $this->patterns;
    }

    /**
     * @return string[]
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    /**
     * @return string[]
     */
    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    /**
     * @return string[]
     */
    public function getSchemes(): array
    {
        return $this->schemes;
    }

    /**
     * @return array<string,mixed>
     */
    public function getDefaults(): array
    {
        return $this->defaults;
    }

    /**
     * @param array<string,mixed> $params
     *
     * @throws InvalidAnnotationException
     */
    private function assertParamsContainValidName(array $params): void
    {
        if (null === $params['name']) {
            return;
        }

        if (empty($params['name']) || !\is_string($params['name'])) {
            throw new InvalidAnnotationException(\sprintf(
                '@Route.name must %s.',
                empty($params['name']) ? 'be not an empty string' : 'contain only a string'
            ));
        }
    }

    /**
     * @param array<string,mixed> $params
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
     * @param array<string,mixed> $params
     *
     * @throws InvalidAnnotationException
     */
    private function assertParamsContainValidMethods(array $params): void
    {
        if (!\is_array($params['methods'])) {
            throw new InvalidAnnotationException('@Route.methods must contain only an array.');
        }

        foreach ($params['methods'] as $method) {
            if (!\is_string($method)) {
                throw new InvalidAnnotationException('@Route.methods must contain only strings.');
            }
        }
    }

    /**
     * @param array<string,mixed> $params
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
     * @param array<string,mixed> $params
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
     * @param array<string,mixed> $params
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
     * @param array<string,mixed> $params
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
