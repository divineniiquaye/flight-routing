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

use Closure;
use Flight\Routing\Interfaces\RouteGroupInterface;
use Flight\Routing\Interfaces\RouteInterface;
use RuntimeException;
use Serializable;
use Throwable;

/**
 * Value object representing a single route.
 *
 * Routes are a combination of path, middleware, and HTTP methods; two routes
 * representing the same path and overlapping HTTP methods are not allowed,
 * while two routes representing the same path and non-overlapping HTTP methods
 * can be used (and should typically resolve to different middleware).
 *
 * Internally, only those three properties are required. However, underlying
 * router implementations may allow or require additional information, such as
 * information defining how to generate a URL from the given route, qualifiers
 * for how segments of a route match, or even default values to use. These may
 * be provided after instantiation via the "defaults" property and related
 * addDefaults() method.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class Route implements Serializable, RouteInterface
{
    use Traits\ControllersTrait;
    use Traits\DefaultsTrait;
    use Traits\DomainsTrait;
    use Traits\GroupsTrait;
    use Traits\MiddlewaresTrait;
    use Traits\PathsTrait;
    use Traits\PatternsTrait;

    /**
     * A Pattern to Locates appropriate route by name, support dynamic route allocation using following pattern:
     * Pattern route:   `pattern/*<controller@action>`
     * Default route: `*<controller@action>`
     * Only action:   `pattern/*<action>`.
     *
     * @var string
     */
    public const RCA_PATTERN = '/^(?:(?P<route>[^(.*)]+)\*<)?(?:(?P<controller>[^@]+)@+)?(?P<action>[a-z_\-]+)\>$/i';

    /**
     * HTTP methods supported by this route.
     *
     * @var string[]
     */
    protected $methods = [];

    /**
     * Route name.
     *
     * @var null|string
     */
    protected $name;

    /**
     * Create a new Route constructor.
     *
     * @param string[]                    $methods  The route HTTP methods
     * @param string                      $pattern  The route pattern
     * @param null|callable|object|string $callable The route callable
     * @param null|RouteGroupInterface    $group    The parent route group
     */
    public function __construct(array $methods, string $pattern, $callable, ?RouteGroupInterface $group = null)
    {
        $this->methods = $methods;

        $this->appendGroupToRoute($group);
        $this->setController($callable);
        $this->setPath($pattern);
    }

    /**
     * @return array
     */
    public function __serialize(): array
    {
        return [
            'path'          => $this->path,
            'prefix'        => $this->prefix,
            'host'          => $this->domain,
            'schemes'       => $this->schemes,
            'namespace'     => $this->namespace,
            'defaults'      => $this->defaults,
            'requirements'  => $this->patterns,
            'methods'       => $this->methods,
            'middlewares'   => $this->middlewares,
            'arguments'     => $this->arguments,
            'group'         => $this->groups,
            'group_append'  => $this->groupAppended,
            'controller'    => $this->controller instanceof Closure ? [$this, 'getController'] : $this->controller,
        ];
    }

    /**
     * @param array $data
     */
    public function __unserialize(array $data): void
    {
        $this->path          = $data['path'];
        $this->prefix        = $data['prefix'];
        $this->domain        = $data['host'];
        $this->defaults      = $data['defaults'];
        $this->schemes       = $data['schemes'];
        $this->patterns      = $data['requirements'];
        $this->methods       = $data['methods'];
        $this->controller    = $data['controller'];
        $this->groupAppended = $data['group_append'];

        if (isset($data['middlewares'])) {
            $this->middlewares = $data['middlewares'];
        }

        if (isset($data['namespace'])) {
            $this->namespace = $data['namespace'];
        }

        if (isset($data['group'])) {
            $this->groups = $data['group'];
        }

        if (isset($data['arguments'])) {
            $this->arguments = $data['arguments'];
        }
    }

    /**
     * {@inheritdoc}
     *
     * @internal
     */
    final public function serialize(): string
    {
        return \serialize($this->__serialize());
    }

    /**
     * {@inheritdoc}
     *
     * @internal
     */
    final public function unserialize($serialized): void
    {
        $this->__unserialize(\unserialize($serialized, null));
    }

    /**
     * @param array $values
     *
     * @throws RuntimeException
     *
     * @internal
     */
    public function fromArray(array $values): void
    {
        try {
            foreach ($values as $key => $value) {
                if (null !== $value) {
                    if ('defaults' === $key) {
                        $this->addDefaults($value);
                    } elseif ('patterns' === $key) {
                        $this->whereArray($value);
                    } else {
                        $this->$key = $this->getValueFromKey($values, $key);
                    }
                }
            }
        } catch (Throwable $exception) {
            throw new RuntimeException($exception->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    /**
     * Add or change the route name.
     *
     * @param string $name
     *
     * @return $this
     */
    public function setName(?string $name): RouteInterface
    {
        if (null === $name) {
            return $this;
        }

        null !== $this->name ? $this->name .= $name : $this->name = $name;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param array  $data
     * @param string $key
     *
     * @return mixed
     */
    private function getValueFromKey(array $data, string $key)
    {
        if (isset($data[$key])) {
            return $data[$key];
        }

        throw new RuntimeException(\sprintf('Missing "%s" parameter in route instance', $key));
    }
}
