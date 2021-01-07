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
                $this->addRouteGroup($collection['class'] ?? null, $collection['method']);

                continue;
            }

            $this->defaultRouteIndex = 0;

            $route = $this->addRoute($collection['class'], $class);
            $this->collector->add($route);
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
     * @param Route                   $annotation
     * @param string|string[]         $handler
     */
    protected function addRoute(Route $annotation, $handler): RouteInterface
    {
        $routeName    = $annotation->getName() ?? $this->getDefaultRouteName($handler);
        $routeMethods = $annotation->getMethods();

        // Incase of API Resource
        if (str_ends_with($routeName, '__restful')) {
            $routeMethods = Router::HTTP_METHODS_STANDARD;
        }

        $route = (new Router($routeName, $routeMethods, $annotation->getPath(), $handler))
            ->setScheme(...$annotation->getSchemes())
            ->setPatterns($annotation->getPatterns())
            ->setDefaults($annotation->getDefaults())
        ->addMiddleware(...$annotation->getMiddlewares());

        if (null !== $annotation->getDomain()) {
            $route->setDomain($annotation->getDomain());
        }

        return $route;
    }

    /**
     * Add a routes from annotation into group
     *
     * @param null|Route $grouping
     * @param mixed[]    $methods
     */
    protected function addRouteGroup(?Route $grouping, array $methods): void
    {
        $routes = $this->mergeAnnotations($methods);

        if ($grouping instanceof Route) {
            foreach ($routes as $route) {
                $route->setDomain($grouping->getDomain() ?? '')
                    ->setName($grouping->getName() . $route->getName())
                    ->setScheme(...$grouping->getSchemes())
                    ->setDefaults($grouping->getDefaults())
                    ->addPrefix($grouping->getPath())
                    ->addMethod(...$grouping->getMethods())
                ->addMiddleware(...$grouping->getMiddlewares());
            }
        }

        $this->collector->addForeach(...$routes);
    }

    /**
     * @param mixed[] $methods
     *
     * @return RouteInterface[]
     */
    protected function mergeAnnotations(array $methods): array
    {
        $this->defaultRouteIndex = 0;

        $routes = [];

        foreach ($methods as [$method, $annotation]) {
            $routes[] = $this->addRoute($annotation, [$method->class, $method->getName()]);
        }

        return $routes;
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
