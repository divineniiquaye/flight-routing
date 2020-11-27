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

use Biurad\Annotations\ListenerInterface;
use Flight\Routing\Interfaces\RouteCollectorInterface;
use ReflectionClass;

class Listener implements ListenerInterface
{
    /** @var RouteCollectorInterface */
    private $collector;

    /** @var int */
    private $defaultRouteIndex = 0;

    public function __construct(RouteCollectorInterface $collector)
    {
        $this->collector = $collector;
    }

    /**
     * {@inheritdoc}
     */
    public function onAnnotation(array $annotations)
    {
        foreach ($annotations as $class => $collection) {
            if (isset($collection['method'])) {
                $this->addRouteGroup($collection['class'] ?? null, $collection['method']);

                continue;
            }

            $this->defaultRouteIndex = 0;
            $this->addRoute($this->collector, $collection['class'], $class);
        }

        return $this->collector->getCollection();
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
     * @param RouteCollectorInterface $collector
     * @param Route                   $annotation
     * @param string|string[]         $handler
     */
    protected function addRoute(RouteCollectorInterface $collector, Route $annotation, $handler): void
    {
        $routeName    = $annotation->getName() ?? $this->getDefaultRouteName($handler);
        $routeMethods = $annotation->getMethods();

        // Incase of API Resource
        if (str_ends_with($routeName, '__restful')) {
            $routeMethods = $collector::HTTP_METHODS_STANDARD;
        }

        $route = $collector->map($routeName, $routeMethods, $annotation->getPath(), $handler)
        ->setScheme(...$annotation->getSchemes())
        ->setPatterns($annotation->getPatterns())
        ->setDefaults($annotation->getDefaults())
        ->addMiddleware(...$annotation->getMiddlewares());

        if (null !== $annotation->getDomain()) {
            $route->setDomain($annotation->getDomain());
        }
    }

    /**
     * Add a routes from annotation into group
     *
     * @param nullRoute $grouping
     * @param array     $methods
     */
    protected function addRouteGroup(?Route $grouping, array $methods): void
    {
        if (null === $grouping) {
            $this->mergeAnnotations($this->collector, $methods);

            return;
        }

        $group = $this->collector->group(
            function (RouteCollectorInterface $group) use ($methods): void {
                $this->mergeAnnotations($group, $methods);
            }
        )
        ->addMethod(...$grouping->getMethods())
        ->addPrefix($grouping->getPath())
        ->addScheme(...$grouping->getSchemes())
        ->addMiddleware(...$grouping->getMiddlewares())
        ->setDefaults($grouping->getDefaults());

        if (null !== $grouping->getName()) {
            $group->setName($grouping->getName());
        }

        if (null !== $grouping->getDomain()) {
            $group->addDomain($grouping->getDomain());
        }
    }

    /**
     * @param RouteCollectorInterface $route
     * @param mixed[]                 $methods
     */
    protected function mergeAnnotations(RouteCollectorInterface $route, array $methods): void
    {
        $this->defaultRouteIndex = 0;

        foreach ($methods as [$method, $annotation]) {
            $this->addRoute($route, $annotation, [$method->class, $method->getName()]);
        }
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
        $classReflection = new ReflectionClass(\is_string($handler) ? $handler : $handler[0]);
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
