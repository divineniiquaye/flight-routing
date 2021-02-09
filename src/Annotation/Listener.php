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
use Flight\Routing\Route as BaseRoute;
use Flight\Routing\RouteCollection;
use Flight\Routing\Router;

class Listener implements ListenerInterface
{
    /** @var RouteCollection */
    private $collector;

    /**
     * @param null|RouteCollection $collector
     */
    public function __construct(?RouteCollection $collector = null)
    {
        $this->collector = $collector ?? new RouteCollection();
    }

    /**
     * {@inheritdoc}
     *
     * @param array<string,array<string,mixed>> $annotations
     */
    public function onAnnotation(array $annotations): RouteCollection
    {
        foreach ($annotations as $class => $collection) {
            if (isset($collection['method'])) {
                $this->addRouteGroup($collection['class'] ?? [], $collection['method']);

                continue;
            }

            /** @var Route $annotation */
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
     * @return BaseRoute
     */
    protected function addRoute(Route $annotation, $handler, ?Route $group = null): BaseRoute
    {
        if (null === $path = $annotation->getPath()) {
            throw new InvalidAnnotationException('@Route.path must not be left empty.');
        }

        $route   = new BaseRoute($path, '', $handler);
        $methods = $annotation->getMethods();

        if (null === $name = $annotation->getName()) {
            $name = $base = $route->generateRouteName('annotated_');
            $i    = 0;

            while ($this->collector->find($name)) {
                $name = $base . '_' . ++$i;
            }
        }

        if (str_starts_with($path, 'api://') && empty($methods)) {
            $methods = Router::HTTP_METHODS_STANDARD;
        }

        if (!empty($methods)) {
            $route->method(...$methods);
        }

        $route->bind($name)->scheme(...$annotation->getSchemes())
            ->middleware(...$annotation->getMiddlewares())
            ->defaults($annotation->getDefaults())
        ->asserts($annotation->getPatterns());

        if (null !== $annotation->getDomain()) {
            $route->domain($annotation->getDomain());
        }

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
        $routes = [];

        foreach ($methods as [$method, $annotation]) {
            $routes[] = $this->addRoute($annotation, [$method->class, $method->getName()], $group);
        }

        $this->collector->add(...$routes);
    }

    /**
     * @param Route     $group
     * @param BaseRoute $route
     *
     * @return BaseRoute
     */
    protected function mergeGroup(Route $group, BaseRoute $route): BaseRoute
    {
        $route = $route->bind($group->getName() . $route->get('name'))
            ->scheme(...$group->getSchemes())
            ->prefix($group->getPath() ?? '')
            ->method(...$group->getMethods())
            ->middleware(...$group->getMiddlewares())
            ->defaults($group->getDefaults())
        ->asserts($group->getPatterns());

        if (null !== $group->getDomain()) {
            $route->domain($group->getDomain());
        }

        return $route;
    }
}
