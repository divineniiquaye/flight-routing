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
use Psr\SimpleCache\CacheInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use RegexIterator;
use Spiral\Annotations\AnnotationLocator;
use Throwable;

class RouteLoader
{
    /** @var RouteCollectorInterface */
    private $collector;

    /** @var AnnotationLocator|AnnotationReader */
    private $annotation;

    /** @var null|CacheInterface */
    private $cache;

    /** @var string[] */
    private $resources = [];

    /**
     * @param RouteCollectorInterface            $collector
     * @param AnnotationLocator|AnnotationReader $reader
     */
    public function __construct(RouteCollectorInterface $collector, $reader = null)
    {
        $this->collector  = $collector;
        $this->annotation = $reader ?? new SimpleAnnotationReader();

        if ($this->annotation instanceof SimpleAnnotationReader) {
            $this->annotation->addNamespace('Flight\Routing\Annotation');
        }
    }

    /**
     * Sets the given cache to the loader
     *
     * @param CacheInterface $cache
     */
    public function setCache(CacheInterface $cache): void
    {
        $this->cache = $cache;
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

        foreach ($this->resources as $resource) {
            if ($this->annotation instanceof AnnotationReader && \is_dir($resource)) {
                $annotations += $this->fetchAnnotations($resource);

                continue;
            }

            if (!\file_exists($resource) || \is_dir($resource)) {
                continue;
            }

            (function () use ($resource): void {
                require $resource;
            })->call($this->collector);
        }

        if ($this->annotation instanceof AnnotationLocator) {
            $annotations = $this->annotationsLocator();
        }

        foreach ($annotations as $class => $collection) {
            if (isset($collection['method'])) {
                $this->addRouteGroup($collection['global'] ?? null, $collection['method']);

                continue;
            }

            $this->addRoute($this->collector, $collection['global'], $class);
        }

        return $this->collector->getCollection();
    }

    /**
     * Add a route from annotation
     *
     * @param RouteCollectorInterface $collector
     * @param Annotation\Route        $annotation
     * @param string|string[]         $handler
     */
    private function addRoute(RouteCollectorInterface $collector, Annotation\Route $annotation, $handler): void
    {
        $route = $collector->map(
            $annotation->getName() ?? $this->getDefaultRouteName($handler),
            $annotation->getMethods(),
            $annotation->getPath(),
            $handler
        )
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
        $methodRoutes = function (RouteCollectorInterface $route) use ($methods): void {
            /**
             * @var Annotation\Route $annotation
             * @var ReflectionMethod $method
             */
            foreach ($methods as [$method, $annotation]) {
                $this->addRoute($route, $annotation, [$method->class, $method->getName()]);
            }
        };

        if (null === $grouping) {
            ($methodRoutes)($this->collector);

            return;
        }

        $group = $this->collector->group(
            function (RouteCollectorInterface $group) use ($methodRoutes): void {
                ($methodRoutes)($group);
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
     * Fetches annotations for the given resource
     *
     * @param string $resource
     *
     * @return mixed[]
     */
    private function fetchAnnotations(string $resource): array
    {
        if (!$this->cache instanceof CacheInterface) {
            return $this->findAnnotations($resource);
        }

        // some cache stores may have character restrictions for a key...
        $key = \hash('md5', $resource);

        if (!$this->cache->has($key)) {
            $value = $this->findAnnotations($resource);

            // TTL should be set at the storage...
            $this->cache->set($key, $value);
        }

        return $this->cache->get($key);
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
        $classes     = $this->findClasses($resource);
        $annotations = [];

        foreach ($classes as $class) {
            $classReflection = new ReflectionClass($class);

            if ($classReflection->isAbstract()) {
                throw new InvalidAnnotationException(\sprintf(
                    'Annotations from class "%s" cannot be read as it is abstract.',
                    $classReflection->getName()
                ));
            }
            $annotationClass = $this->annotation->getClassAnnotation($classReflection, Annotation\Route::class);

            if (null !== $annotationClass) {
                $annotations[$class]['global'] = $annotationClass;
            }

            foreach ($classReflection->getMethods() as $method) {
                if ($method->isAbstract() || $method->isPrivate() || $method->isProtected()) {
                    continue;
                }

                foreach ($this->annotation->getMethodAnnotations($method) as $annotationMethod) {
                    if ($annotationMethod instanceof Annotation\Route) {
                        $annotations[$class]['method'][] = [$method, $annotationMethod];
                    }
                }
            }
        }

        return $annotations;
    }

    /**
     * Finds annotations using spiral annotations
     *
     * @return mixed[]
     */
    private function annotationsLocator(): array
    {
        $annotations = [];

        foreach ($this->annotation->findClasses(Annotation\Route::class) as $class) {
            $classReflection = $class->getClass();

            $annotations[$classReflection->name]['global'] = $class->getAnnotation();
        }

        foreach ($this->annotation->findMethods(Annotation\Route::class) as $method) {
            $methodReflection = $method->getMethod();

            if ($methodReflection->isPrivate() || $methodReflection->isProtected()) {
                continue;
            }

            $annotations[$method->getClass()->name]['method'][] = [$methodReflection, $method->getAnnotation()];
        }

        return $annotations;
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

        return \strtolower($name);
    }

    /**
     * Finds classes in the given resource
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
