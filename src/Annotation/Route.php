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

use Biurad\Annotations\InvalidAnnotationException;

/**
 * Annotation class for @Route().
 *
 * @Annotation
 *
 * On PHP 7.2+ Attributes are supported except you want to use Doctrine annotations:
 * ```php
 *     #[Route('/blog/{_locale}', name: 'blog', defaults: ['_locale' => 'en'])]
 *     class Blog
 *     {
 *         #[Route('/', name: '_index', methods: ['GET', 'HEAD'] schemes: 'https')]
 *         public function index()
 *         {
 *         }
 *         #[Route('/{id}', name: '_post', methods: 'POST' where: ["id" => '\d+'])]
 *         public function show()
 *         {
 *         }
 *     }
 * ```
 *
 * @Target({"CLASS", "METHOD", "FUNCTION"})
 */
#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
final class Route
{
    private const ATTRIBUTES = [
        'path' => 'string',
        'name' => 'string',
        'resource' => 'string',
        'patterns' => 'array',
        'defaults' => 'array',
        'methods' => 'string|string[]',
        'domain' => 'string|string[]',
        'schemes' => 'string|string[]',
        'middlewares' => 'string|string[]',
    ];

    /** @var string|null @Required */
    public $path = null;

    /** @var string|null @Required */
    public $name = null;

    /** @var string[] @Required */
    public $methods = [];

    /** @var string[] */
    public $domain = [];

    /** @var array<string,true> */
    public $schemes = [];

    /** @var string[] */
    public $middlewares = [];

    /** @var array<string,string> */
    public $patterns = [];

    /** @var array<string,mixed> */
    public $defaults = [];

    /** @var string|null */
    public $resource = null;

    /**
     * @param array|string    $params      data array managed by the Doctrine Annotations library or the path
     * @param string|string[] $methods
     * @param string|string[] $schemes
     * @param string|string[] $domain
     * @param string[]        $where
     * @param string|string[] $middlewares
     * @param string[]        $defaults
     */
    public function __construct(
        $params = [],
        ?string $path = null,
        string $name = null,
        $methods = [],
        $schemes = [],
        $domain = [],
        $middlewares = [],
        array $where = [],
        array $defaults = [],
        string $resource = null
    ) {
        if (\is_array($params) && isset($params['value'])) {
            $params['path'] = $params['value'];
            unset($params['value']);
        } elseif (\is_string($params)) {
            $params = ['path' => $params];
        }

        $parameters = [
            'middlewares' => $middlewares,
            'defaults' => $defaults,
            'schemes' => $schemes,
            'patterns' => $where,
            'methods' => $methods,
            'domain' => $domain,
            'name' => $name,
            'path' => $path,
            'resource' => $resource,
        ];
        $parameters += $params; // Replace defaults with $params

        foreach ($params as $id => $value) {
            if (null === $validate = self::ATTRIBUTES[$id] ?? null) {
                throw new InvalidAnnotationException(\sprintf('The @Route.%s is unsupported. Allowed param keys are ["%s"].', $id, \implode('", "', \array_keys(self::ATTRIBUTES))));
            }

            if ('' === $value || null === $value) {
                continue;
            }

            if ('string' === $validate) {
                if (!\is_string($value)) {
                    throw new InvalidAnnotationException(\sprintf('@Route.%s must contain only a type of %s.', $id, $validate));
                }
                $this->{$id} = $value;

                continue;
            }

            foreach ((array) $value as $key => $param) {
                if (!\is_string($key) && 'array' === $validate) {
                    throw new InvalidAnnotationException(\sprintf('@Route.%s must contain a sequence %s of string keys and values. eg: [key => value]', $id, $validate));
                }

                if ('string|string[]' === $validate) {
                    if (!\is_string($param)) {
                        throw new InvalidAnnotationException(\sprintf('@Route.%s must contain only an array list of strings.', $id));
                    }

                    if ('schemes' !== $id) {
                        $this->{$id}[] = $param;
                    } else {
                        $this->schemes[$param] = true;
                    }

                    continue;
                }

                $this->{$id}[$key] = $param;
            }
        }
    }

    /**
     * Merge a route annotation into this route.
     */
    public function clone(self $annotation): void
    {
        $this->methods = \array_merge($annotation->methods, $this->methods);
        $this->domain = \array_merge($annotation->domain, $this->domain);
        $this->schemes = \array_merge($annotation->schemes, $this->schemes);
        $this->middlewares = \array_merge($annotation->middlewares, $this->middlewares);
        $this->patterns = \array_merge($annotation->patterns, $this->patterns);
        $this->defaults = \array_merge($annotation->defaults, $this->defaults);
    }
}
