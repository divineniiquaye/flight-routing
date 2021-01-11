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

use Flight\Routing\Interfaces\RouteInterface;

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
class Route implements RouteInterface
{
    use Traits\RouteTrait;
    use Traits\CastingTrait;

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
     * Create a new Route constructor.
     *
     * @param string                                   $name    The route name
     * @param string[]                                 $methods The route HTTP methods
     * @param string                                   $pattern The route pattern
     * @param null|array<mixed,string>|callable|string $handler The route callable
     */
    public function __construct(string $name, array $methods, string $pattern, $handler)
    {
        $this->name       = $name;
        $this->controller = null === $handler ? '' : $handler;
        $this->methods    = \array_map('strtoupper', $methods);
        $this->path       = $this->castRoute($pattern);
    }

    /**
     * @internal This is handled different by router
     *
     * @param array $properties
     */
    public static function __set_state(array $properties)
    {
        $controller = $properties[4];

        $recovered = new self($properties[0], $properties[1], $properties[2], $controller);
        $recovered->setDomain($properties[3]);
        $recovered->addMiddleware(...$properties[5]);
        $recovered->setPatterns($properties[6]);
        $recovered->setDefaults($properties[7]);
        $recovered->setArguments($properties[8]);

        return $recovered;
    }
}
