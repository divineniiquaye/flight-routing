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
use Biurad\Annotations\Locate\Class_;
use Flight\Routing\{Route as BaseRoute, RouteCollection};

/**
 * The Biurad Annotation's Listener bridge.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class Listener implements ListenerInterface
{
    private RouteCollection $collector;

    private ?string $unNamedPrefix;

    /** @var array<string,int> */
    private array $defaultUnnamedIndex = [];

    /**
     * @param string $unNamedPrefix Setting a prefix or empty string will generate a name for all routes.
     *                              If set to null, only named grouped class routes names will be generated.
     */
    public function __construct(RouteCollection $collector = null, ?string $unNamedPrefix = '')
    {
        $this->unNamedPrefix = $unNamedPrefix;
        $this->collector = $collector ?? new RouteCollection();
    }

    /**
     * {@inheritdoc}
     */
    public function load(array $annotations): RouteCollection
    {
        $foundAnnotations = [];

        foreach ($annotations as $annotation) {
            if ($annotation instanceof Class_) {
                $methodAnnotations = [];

                foreach ($annotation->methods as $method) {
                    $controller = [$method->getReflection()->class, (string) $method];
                    $this->getRoutes($method->getAnnotation(), $controller, $methodAnnotations);
                }

                if (!empty($methodAnnotations)) {
                    $this->addRoute($annotation->getAnnotation(), $methodAnnotations, $foundAnnotations);

                    continue;
                }
            }

            $this->getRoutes($annotation->getAnnotation(), (string) $annotation, $foundAnnotations, $annotation instanceof Class_);
        }

        return $this->collector->routes($foundAnnotations);
    }

    /**
     * {@inheritdoc}
     */
    public function getAnnotation(): string
    {
        return 'Flight\Routing\Annotation\Route';
    }

    /**
     * Add a route from class annotated methods.
     *
     * @param iterable<Route>      $classAnnotations
     * @param array<int,BaseRoute> $methodAnnotations
     * @param array<int,BaseRoute> $foundAnnotations
     */
    protected function addRoute(iterable $classAnnotations, array $methodAnnotations, array &$foundAnnotations): void
    {
        foreach ($methodAnnotations as $methodAnnotation) {
            if (!empty($classAnnotations)) {
                foreach ($classAnnotations as $classAnnotation) {
                    if (null !== $classAnnotation->resource) {
                        throw new InvalidAnnotationException('Restful annotated class cannot contain annotated method(s).');
                    }

                    $annotatedMethod = clone $methodAnnotation->method(...$classAnnotation->methods)
                        ->scheme(...$classAnnotation->schemes)
                        ->domain(...$classAnnotation->hosts)
                        ->defaults($classAnnotation->defaults)
                        ->asserts($classAnnotation->patterns);

                    if (null !== $classAnnotation->path) {
                        $annotatedMethod->prefix($classAnnotation->path);
                    }

                    if (!empty($routeName = $this->resolveRouteName($classAnnotation->name, $annotatedMethod, true))) {
                        $annotatedMethod->bind($routeName);
                    }

                    $foundAnnotations[] = $annotatedMethod;
                }

                continue;
            }

            if (!empty($routeName = $this->resolveRouteName(null, $methodAnnotation))) {
                $methodAnnotation->bind($routeName);
            }

            $foundAnnotations[] = $methodAnnotation;
        }
    }

    /**
     * @param iterable<Route> $annotations
     * @param mixed           $handler
     * @param BaseRoute[]     $foundAnnotations
     */
    protected function getRoutes(iterable $annotations, $handler, array &$foundAnnotations, bool $single = false): void
    {
        foreach ($annotations as $annotation) {
            if (!$single && null !== $annotation->resource) {
                throw new InvalidAnnotationException('Restful annotation is only supported on classes.');
            }

            $route = $annotation->getRoute($handler);

            if ($single && $routeName = $this->resolveRouteName(null, $route)) {
                $route->bind($routeName);
            }

            $foundAnnotations[] = $route;
        }
    }

    /**
     * Resolve route naming.
     */
    private function resolveRouteName(?string $prefix, BaseRoute $route, bool $force = false): string
    {
        $name = $route->getName();

        if ((null !== $this->unNamedPrefix || $force) && empty($name)) {
            $name = $base = $prefix . $route->generateRouteName($this->unNamedPrefix ?? '');

            if (isset($this->defaultUnnamedIndex[$name])) {
                $name = $base . '_' . ++$this->defaultUnnamedIndex[$name];
            } else {
                $this->defaultUnnamedIndex[$name] = 0;
            }

            return $name;
        }

        return (string) $prefix . $name;
    }
}
