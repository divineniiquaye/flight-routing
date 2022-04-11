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
            $reflection = $annotation['type'];
            $methodAnnotations = [];

            if (empty($methods = $annotation['methods'] ?? [])) {
                $this->getRoutes($annotation['attributes'], $reflection->name, $foundAnnotations, $reflection instanceof \ReflectionClass);
                continue;
            }

            foreach ($methods as $method) {
                $controller = ($m = $method['type'])->isStatic() ? $reflection->name . '::' . $m->name : [$reflection->name, $m->name];
                $this->getRoutes($method['attributes'], $controller, $methodAnnotations);
            }

            foreach ($methodAnnotations as $methodAnnotation) {
                if (empty($annotation['attributes'])) {
                    if (!empty($routeName = $this->resolveRouteName(null, $methodAnnotation))) {
                        $methodAnnotation->bind($routeName);
                    }

                    $foundAnnotations[] = $methodAnnotation;
                    continue;
                }

                foreach ($annotation['attributes'] as $classAnnotation) {
                    if (null !== $classAnnotation->resource) {
                        throw new InvalidAnnotationException('Restful annotated class cannot contain annotated method(s).');
                    }

                    $annotatedMethod = clone $methodAnnotation->method(...$classAnnotation->methods)
                            ->scheme(...$classAnnotation->schemes)
                            ->domain(...$classAnnotation->hosts)
                            ->defaults($classAnnotation->defaults)
                            ->arguments($classAnnotation->arguments)
                            ->asserts($classAnnotation->patterns);

                    if (null !== $classAnnotation->path) {
                        $annotatedMethod->prefix($classAnnotation->path);
                    }

                    if (!empty($routeName = $this->resolveRouteName($classAnnotation->name, $annotatedMethod, true))) {
                        $annotatedMethod->bind($routeName);
                    }

                    $foundAnnotations[] = $annotatedMethod;
                }
            }
        }

        return $this->collector->routes($foundAnnotations);
    }

    /**
     * {@inheritdoc}
     */
    public function getAnnotations(): array
    {
        return ['Flight\Routing\Annotation\Route'];
    }

    /**
     * @param array<int,Route> $annotations
     * @param mixed            $handler
     * @param BaseRoute[]      $foundAnnotations
     */
    protected function getRoutes(array $annotations, $handler, array &$foundAnnotations, bool $single = false): void
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
