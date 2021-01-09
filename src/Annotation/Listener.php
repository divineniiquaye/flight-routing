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
use Biurad\Annotations\ListenerInterface;
use Flight\Routing\Interfaces\RouteInterface;
use Flight\Routing\Interfaces\RouteListInterface;
use Flight\Routing\Route as Router;
use Flight\Routing\RouteList;
use ReflectionClass;

class Listener implements ListenerInterface
{
    /** @var RouteListInterface */
    private $collector;

    /** @var int */
    private $defaultRouteIndex = 0;

    /**
     * @param null|RouteList $collector
     */
    public function __construct(?RouteListInterface $collector = null)
    {
        $this->collector = $collector ?? new RouteList();
    }

    /**
     * {@inheritdoc}
     *
     * @param array<string,array<string,mixed>> $annotations
     */
    public function onAnnotation(array $annotations): RouteListInterface
    {
        foreach ($annotations as $class => $collection) {
            if (isset($collection['method'])) {
                $this->addRouteGroup($collection['class'] ?? [], $collection['method']);

                continue;
            }

            $this->defaultRouteIndex = 0;

            foreach ($collection['class'] ?? [] as $annotation) {
                $this->collector->add($this->addRoute($annotation, $class));
            }
        }

        return $this->collector;
    }

    /**
     * {@inheritdoc}
     */
    public function getArguments(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getAnnotation(): string
    {
        return 'Flight\Routing\Annotation\Route';
    }

    /**
     * Add a route from annotation
     *
     * @param Route           $annotation
     * @param string|string[] $handler
     * @param null|Route      $group
     *
     * @return RouteInterface
     */
    protected function addRoute(Route $annotation, $handler, ?Route $group = null): RouteInterface
    {
        if (null === $annotation->getPath()) {
            throw new InvalidAnnotationException('@Route.path must not be left empty.');
        }

        $name    = $annotation->getName() ?? $this->getDefaultRouteName($handler);
        $methods = str_ends_with($name, '__restful') ? Router::HTTP_METHODS_STANDARD : $annotation->getMethods();
        $route   = new Router($name, $methods, $annotation->getPath(), $handler);

        $route->setDomain($annotation->getDomain() ?? '')
            ->setScheme(...$annotation->getSchemes())
            ->setPatterns($annotation->getPatterns())
            ->setDefaults($annotation->getDefaults())
        ->addMiddleware(...$annotation->getMiddlewares());

        if (null !== $group) {
            $route = $this->mergeGroup($group, $route);
        }

        return $route;
    }

    /**
     * Add a routes from annotation into group
     *
     * @param Route[] $grouping
     * @param mixed[] $methods
     */
    protected function addRouteGroup(array $grouping, array $methods): void
    {
        if (!empty($grouping)) {
            foreach ($grouping as $group) {
                $this->mergeAnnotations($methods, $group);
            }

            return;
        }

        $this->mergeAnnotations($methods);
    }

    /**
     * @param mixed[]    $methods
     * @param null|Route $group
     */
    protected function mergeAnnotations(array $methods, ?Route $group = null): void
    {
        $this->defaultRouteIndex = 0;

        $routes = [];

        foreach ($methods as [$method, $annotation]) {
            $routes[] = $this->addRoute($annotation, [$method->class, $method->getName()], $group);
        }

        $this->collector->addForeach(...$routes);
    }

    /**
     * @param Route          $group
     * @param RouteInterface $route
     *
     * @return RouteInterface
     */
    protected function mergeGroup(Route $group, RouteInterface $route): RouteInterface
    {
        $route = $route->setDomain($group->getDomain() ?? '')
            ->setName($group->getName() . $route->getName())
            ->setScheme(...$group->getSchemes())
            ->setDefaults($group->getDefaults())
            ->setPatterns($group->getPatterns())
            ->addPrefix($group->getPath() ?? '')
            ->addMethod(...$group->getMethods())
        ->addMiddleware(...$group->getMiddlewares());

        return $route;
    }

    /**
     * Gets the default route name for a class method.
     *
     * @param string|string[] $handler
     *
     * @return string
     */
    private function getDefaultRouteName($handler): string
    {
        $classReflection = new ReflectionClass(\is_array($handler) ? $handler[0] : $handler);
        $name            = \str_replace('\\', '_', $classReflection->name);

        if (\is_array($handler) || $classReflection->hasMethod('__invoke')) {
            $name .= '_' . $handler[1] ?? '__invoke';
        }

        if ($this->defaultRouteIndex > 0) {
            $name .= '_' . $this->defaultRouteIndex;
        }
        ++$this->defaultRouteIndex;

        return \strtolower($name);
    }
}
