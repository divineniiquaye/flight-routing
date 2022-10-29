<?php

declare(strict_types=1);

/*
 * This file is part of Flight Routing.
 *
 * PHP version 8.0 and above required
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 Divine Niiquaye Ibok (https://divinenii.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flight\Routing\Handlers;

use Flight\Routing\Exceptions\InvalidControllerException;

/**
 * An extendable HTTP Verb-based route handler to provide a RESTful API for a resource.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
final class ResourceHandler
{
    /**
     * @param string $method The method name eg: action -> getAction
     */
    public function __construct(
        private string|object $resource,
        private string $method = 'action'
    ) {
        if (\is_callable($resource) || \is_subclass_of($resource, self::class)) {
            throw new \Flight\Routing\Exceptions\InvalidControllerException(
                'Expected a class string or class object, got a type of "callable" instead'
            );
        }
    }

    /**
     * @return array<int,object|string>
     */
    public function __invoke(string $requestMethod, bool $validate = false): array
    {
        $method = \strtolower($requestMethod).\ucfirst($this->method);

        if (\is_string($class = $this->resource)) {
            $class = \ltrim($class, '\\');
        }

        if ($validate && !\method_exists($class, $method)) {
            $err = 'Method %s() for resource route "%s" is not found.';

            throw new InvalidControllerException(\sprintf($err, $method, \is_object($class) ? $class::class : $class));
        }

        return [$class, $method];
    }

    /**
     * Append a missing namespace to resource class.
     *
     * @internal
     */
    public function namespace(string $namespace): self
    {
        if (!\is_string($resource = $this->resource) || '\\' === $resource[0]) {
            return $this;
        }

        if (!\str_starts_with($resource, $namespace)) {
            $this->resource = $namespace.$resource;
        }

        return $this;
    }
}
