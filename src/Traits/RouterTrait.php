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

namespace Flight\Routing\Traits;

use Biurad\Annotations\LoaderInterface;
use Flight\Routing\DebugRoute;
use Flight\Routing\Interfaces\RouteMatcherInterface;
use Flight\Routing\Route;
use Flight\Routing\RouteCollection;
use Flight\Routing\RouteResolver;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

trait RouterTrait
{
    use MiddlewareTrait;

    /** @var null|RouteMatcherInterface */
    private $matcher;

    /** @var ResponseFactoryInterface */
    private $responseFactory;

    /** @var UriFactoryInterface */
    private $uriFactory;

    /** @var null|DebugRoute */
    private $debug;

    /** @var RouteCollection */
    private $routes;

    /** @var null|Route */
    private $route;

    /** @var RouteResolver */
    private $resolver;

    /** @var mixed[] */
    private $options = [];

    /**
     * Get the profiled routes
     *
     * @return null|DebugRoute
     */
    public function getProfile(): ?DebugRoute
    {
        if ($this->options['debug']) {
            foreach ($this->routes as $route) {
                $this->debug->addProfile(new DebugRoute($route->get('name'), $route));
            }

            return $this->debug;
        }

        return null;
    }

    /**
     * Load routes from annotation.
     *
     * @param LoaderInterface $loader
     */
    public function loadAnnotation(LoaderInterface $loader): void
    {
        $annotations = $loader->load();

        foreach ($annotations as $annotation) {
            if ($annotation instanceof RouteCollection) {
                $this->addRoute(...$annotation);
            }
        }
    }

    /**
     * Get merged default parameters.
     *
     * @param Route $route
     */
    private function mergeDefaults(Route $route): void
    {
        $defaults = $route->get('defaults');
        $param    = $route->get('arguments');
        $excludes = ['_arguments' => true, '_domain' => true];

        foreach ($defaults as $key => $value) {
            if (isset($excludes[$key])) {
                continue;
            }

            if (!isset($param[$key]) || (!\is_int($key) && null !== $value)) {
                $route->argument($key, $value);
            }
        }
    }
}
