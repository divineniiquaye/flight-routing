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

namespace Flight\Routing\Annotation;

use Flight\Routing\Route as BaseRoute;
use Flight\Routing\Handlers\ResourceHandler;

/**
 * Annotation class for @Route().
 *
 * @Annotation
 * @NamedArgumentConstructor
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
#[\Spiral\Attributes\NamedArgumentConstructor]
#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
final class Route
{
    public ?string $path, $name, $resource;
    public array $methods, $hosts, $schemes, $patterns, $defaults, $arguments;

    /**
     * @param string|string[] $methods
     * @param string|string[] $schemes
     * @param string|string[] $hosts
     * @param string[]        $where
     * @param string[]        $defaults
     */
    public function __construct(
        string $path = null,
        string $name = null,
        $methods = [],
        $schemes = [],
        $hosts = [],
        array $where = [],
        array $defaults = [],
        array $attributes = [],
        string $resource = null
    ) {
        $this->path = $path;
        $this->name = $name;
        $this->resource = $resource;
        $this->methods = (array) $methods;
        $this->schemes = (array) $schemes;
        $this->hosts = (array) $hosts;
        $this->patterns = $where;
        $this->defaults = $defaults;
        $this->arguments = $attributes;
    }

    /**
     * @param mixed $handler
     */
    public function getRoute($handler): BaseRoute
    {
        $routeData = [
            'handler' => !empty($this->resource) ? new ResourceHandler($handler, $this->resource) : $handler,
            'name' => $this->name,
            'path' => $this->path,
            'methods' => $this->methods,
            'patterns' => $this->patterns,
            'defaults' => $this->defaults,
            'arguments' => $this->arguments,
        ];

        $route = BaseRoute::__set_state($routeData);

        if (!empty($this->hosts)) {
            $route->domain(...$this->hosts);
        }

        if (!empty($this->schemes)) {
            $route->scheme(...$this->schemes);
        }

        return $route;
    }
}
