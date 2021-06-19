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

namespace Flight\Routing\Handlers;

/**
 * An extendable HTTP Verb-based route handler to provide a RESTful API for a resource.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
final class ResourceHandler
{
    /** @var string|object */
    private $classResource;

    /** @var string */
    private $actionResource;

    /**
     * @param class-string|object $class  of class string or class object
     * @param string              $action The method name eg: action -> getAction
     */
    public function __construct($class, string $action = 'action')
    {
        $this->classResource = $class;
        $this->actionResource = \ucfirst($action);
    }

    /**
     * @internal
     *
     * Append a missing namespace to resource class.
     */
    public function namespace(string $namespace): self
    {
        $resource = $this->classResource;

        if (\is_string($resource) && '\\' === $resource[0]) {
            $this->classResource = $namespace . $resource;
        }

        return $this;
    }

    /**
     * @return string[]
     */
    public function __invoke(string $requestMethod): array
    {
        return [$this->classResource, $requestMethod . $this->actionResource];
    }
}
