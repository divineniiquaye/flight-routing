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

namespace Flight\Routing;

use Doctrine\Common\Annotations\Reader as AnnotationReader;
use Doctrine\Common\Annotations\SimpleAnnotationReader;
use FilesystemIterator;
use Flight\Routing\Exceptions\InvalidAnnotationException;
use Flight\Routing\Interfaces\RouteCollectionInterface;
use Flight\Routing\Interfaces\RouteCollectorInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use RegexIterator;

class RouteLoader
{
    /** @var RouteCollectorInterface */
    private $collector;

    /** @var null|AnnotationReader */
    private $annotation;

    /** @var string[] */
    private $resources = [];

    /** @var int */
    private $defaultRouteIndex = 0;

    /**
     * @param RouteCollectorInterface $collector
     * @param null|AnnotationReader   $reader
     */
    public function __construct(RouteCollectorInterface $collector, ?AnnotationReader $reader = null)
    {
        $this->collector  = $collector;
        $this->annotation = $reader;

        if (null === $reader && \interface_exists(AnnotationReader::class)) {
            $this->annotation = new SimpleAnnotationReader();
        }

        if ($this->annotation instanceof SimpleAnnotationReader) {
            $this->annotation->addNamespace('Flight\Routing\Annotation');
        }
    }

    /**
     * Attaches the given resource to the loader
     *
     * @param string $resource
     */
    public function attach(string $resource): void
    {
        $this->resources[] = $resource;
    }

    /**
     * Attaches the given array with resources to the loader
     *
     * @param string[] $resources
     */
    public function attachArray(array $resources): void
    {
        foreach ($resources as $resource) {
            $this->attach($resource);
        }
    }

    /**
     * Loads routes from attached resources
     *
     * @return RouteCollectionInterface
     */
    public function load(): RouteCollectionInterface
    {
        $annotations = [];
        $collector   = clone $this->collector;

        foreach ($this->resources as $resource) {
            if (class_exists($resource) || \is_dir($resource)) {
                $annotations += $this->findAnnotations($resource);

                continue;
            }

            if (!\file_exists($resource) || \is_dir($resource)) {
                continue;
            }

            (function () use ($resource, $collector): void {
                require $resource;
            })->call($this->collector);
        }

        return $this->resolveAnnotations($collector, $annotations);
    }

    /**
     * Add a route from annotation
     *
     * @param RouteCollectorInterface             $collector
     * @param Annotation\Route|Annotation\Route[] $annotation
     * @param string|string[]                     $handler
     */
    private function addRoute(RouteCollectorInterface $collector, Annotation\Route $annotation, $handler): void
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
     * @param null|Annotation\Route $grouping
     * @param array                 $methods
     */
    private function addRouteGroup(?Annotation\Route $grouping, array $methods): void
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
     * @param RouteCollectorInterface $collector
     * @param array<string,mixed>     $annotations
     */
    private function resolveAnnotations(RouteCollectorInterface $collector, array $annotations): RouteCollectionInterface
    {
        foreach ($annotations as $class => $collection) {
            if (isset($collection['method'])) {
                $this->addRouteGroup($collection['global'] ?? null, $collection['method']);

                continue;
            }
            $this->defaultRouteIndex = 0;

            foreach ($this->getAnnotations(new ReflectionClass($class)) as $annotation) {
                $this->addRoute($this->collector, $annotation, $class);
            }
        }

        \gc_mem_caches();

        return $collector->getCollection();
    }

    /**
     * @param RouteCollectorInterface $route
     * @param mixed[]                 $methods
     */
    private function mergeAnnotations(RouteCollectorInterface $route, array $methods): void
    {
        foreach ($methods as [$method, $annotation]) {
            $this->addRoute($route, $annotation, [$method->class, $method->getName()]);
        }
    }

    /**
     * Finds annotations in the given resource
     *
     * @param string $resource
     *
     * @return mixed[]
     */
    private function findAnnotations(string $resource): array
    {
        $classes = $annotations = [];

        if (is_dir($resource)) {
            $classes = array_merge($this->findClasses($resource), $classes);
        } elseif (class_exists($resource)) {
            $classes[] = $resource;
        }

        foreach ($classes as $class) {
            $classReflection = new ReflectionClass($class);

            if ($classReflection->isAbstract()) {
                throw new InvalidAnnotationException(\sprintf(
                    'Annotations from class "%s" cannot be read as it is abstract.',
                    $classReflection->getName()
                ));
            }

            if (
                \PHP_VERSION_ID >= 80000 &&
                ($attribute = $classReflection->getAttributes(Annotation\Route::class)[0] ?? null)
            ) {
                $annotations[$class]['global'] = $attribute->newInstance();
            }

            if (!isset($annotations[$class]) && $this->annotation instanceof AnnotationReader) {
                $annotations[$class]['global'] = $this->annotation->getClassAnnotation(
                    $classReflection,
                    Annotation\Route::class
                );
            }

            foreach ($classReflection->getMethods() as $method) {
                if ($method->isAbstract() || $method->isPrivate() || $method->isProtected()) {
                    continue;
                }
                $this->defaultRouteIndex = 0;

                foreach ($this->getAnnotations($method) as $annotation) {
                    $annotations[$method->class]['method'][] = [$method, $annotation];
                }
            }
        }

        return $annotations;
    }

    /**
     * @param ReflectionClass|ReflectionMethod $reflection
     *
     * @return Annotation\Route[]|iterable
     */
    private function getAnnotations(object $reflection): iterable
    {
        if (\PHP_VERSION_ID >= 80000) {
            foreach ($reflection->getAttributes(Annotation\Route::class) as $attribute) {
                yield $attribute->newInstance();
            }
        }

        if (null === $this->annotation) {
            return;
        }

        $anntotations = $reflection instanceof ReflectionClass
            ? $this->annotation->getClassAnnotations($reflection)
            : $this->annotation->getMethodAnnotations($reflection);

        foreach ($anntotations as $annotation) {
            if ($annotation instanceof Annotation\Route) {
                yield $annotation;
            }
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
            $name .= '_'.$this->defaultRouteIndex;
        }
        ++$this->defaultRouteIndex;

        return \strtolower($name);
    }

    /**
     * Finds classes in the given resource directory
     *
     * @param string $resource
     *
     * @return string[]
     */
    private function findClasses(string $resource): array
    {
        $files    = $this->findFiles($resource);
        $declared = \get_declared_classes();

        foreach ($files as $file) {
            require_once $file;
        }

        return \array_diff(\get_declared_classes(), $declared);
    }

    /**
     * Finds files in the given resource
     *
     * @param string $resource
     *
     * @return string[]
     */
    private function findFiles(string $resource): array
    {
        $flags = FilesystemIterator::CURRENT_AS_PATHNAME;

        $directory = new RecursiveDirectoryIterator($resource, $flags);
        $iterator  = new RecursiveIteratorIterator($directory);
        $files     = new RegexIterator($iterator, '/\.php$/');

        return \iterator_to_array($files);
    }
}
