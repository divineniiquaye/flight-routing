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

use Biurad\Annotations\{InvalidAnnotationException, ListenerInterface};
use Flight\Routing\Handlers\ResourceHandler;
use Flight\Routing\RouteCollection;

/**
 * The Biurad Annotation's Listener bridge.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class Listener implements ListenerInterface
{
    private RouteCollection $collector;

    public function __construct(RouteCollection $collector = null)
    {
        $this->collector = $collector ?? new RouteCollection();
    }

    /**
     * {@inheritdoc}
     */
    public function load(array $annotations): RouteCollection
    {
        foreach ($annotations as $annotation) {
            $reflection = $annotation['type'];
            $attributes = $annotation['attributes'] ?? [];

            if (empty($methods = $annotation['methods'] ?? [])) {
                foreach ($attributes as $route) {
                    $this->addRoute($this->collector, $route, $reflection->getName());
                }
                continue;
            }

            if (empty($attributes)) {
                foreach ($methods as $method) {
                    foreach (($method['attributes'] ?? []) as $route) {
                        $controller = ($m = $method['type'])->isStatic() ? $reflection->name.'::'.$m->name : [$reflection->name, $m->name];
                        $this->addRoute($this->collector, $route, $controller);
                    }
                }
                continue;
            }

            foreach ($attributes as $classAnnotation) {
                $group = empty($classAnnotation->resource)
                    ? $this->addRoute($this->collector->group($classAnnotation->name, return: true), $classAnnotation, true)
                    : throw new InvalidAnnotationException('Restful annotated class cannot contain annotated method(s).');

                foreach ($methods as $method) {
                    foreach (($method['attributes'] ?? []) as $methodAnnotation) {
                        $controller = ($m = $method['type'])->isStatic() ? $reflection->name.'::'.$m->name : [$reflection->name, $m->name];
                        $this->addRoute($group, $methodAnnotation, $controller);
                    }
                }
            }
        }

        return $this->collector;
    }

    /**
     * {@inheritdoc}
     */
    public function getAnnotations(): array
    {
        return ['Flight\Routing\Annotation\Route'];
    }

    protected function addRoute(RouteCollection $collection, Route $route, mixed $handler): RouteCollection
    {
        if (true !== $handler) {
            if (empty($route->path)) {
                throw new InvalidAnnotationException('Attributed method route path empty');
            }

            if (!empty($route->resource)) {
                $handler = !\is_string($handler) || !\class_exists($handler)
                    ? throw new InvalidAnnotationException('Restful routing is only supported on attribute route classes.')
                    : new ResourceHandler($handler, $route->resource);
            }

            $collection->add($route->path, $route->methods ?: ['GET'], $handler);

            if (!empty($route->name)) {
                $collection->bind($route->name);
            }
        } else {
            if (!empty($route->path)) {
                $collection->prefix($route->path);
            }

            if (!empty($route->methods)) {
                $collection->method(...$route->methods);
            }
        }

        if (!empty($route->schemes)) {
            $collection->scheme(...$route->schemes);
        }

        if (!empty($route->hosts)) {
            $collection->domain(...$route->hosts);
        }

        if (!empty($route->where)) {
            $collection->placeholders($route->where);
        }

        if (!empty($route->defaults)) {
            $collection->defaults($route->defaults);
        }

        if (!empty($route->arguments)) {
            $collection->arguments($route->arguments);
        }

        return $collection;
    }
}
