<?php

declare(strict_types=1);

/*
 * This code is under BSD 3-Clause "New" or "Revised" License.
 *
 * PHP version 7 and above required
 *
 * @category  RoutingManager
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 Biurad Group (https://biurad.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * @link      https://www.biurad.com/projects/routingmanager
 * @since     Version 0.1
 */

namespace Flight\Routing\Interfaces;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

interface RouteInterface
{
    /**
     * Get route methods
     *
     * @return string[]
     */
    public function getMethods(): array;

    /**
     * Get the route patternised path.
     *
     * @return string
     */
    public function getPath(): string;

    /**
     * Get the Controller used on route.
     *
     * @return mixed
     */
    public function getController();

    /**
     * Set a regular expression requirement on the route.
     *
     * @param string $name
     * @param string $expression
     *
     * @return $this
     */
    public function addPattern(string $name, string $expression): RouteInterface;

    /**
     * Get route requirements
     */
    public function getPatterns(): array;

    /**
     * Get route domain, same as host.
     *
     * @return string
     */
    public function getDomain(): string;

    /**
     * Get or set the domain for the route.
     *
     * @param string|null $domain
     *
     * @return RouteInterface
     */
    public function addDomain(?string $domain): RouteInterface;

    /**
     * Get route name
     *
     * @return null|string
     */
    public function getName(): ?string;

    /**
     * Set route name
     *
     * @param string $name
     *
     * @return static
     */
    public function setName(string $name): RouteInterface;

    /**
     * Retrieve a specific route argument
     *
     * @param string      $name
     * @param string|null $default
     *
     * @return string|null
     */
    public function getArgument(string $name, ?string $default = null): ?string;

    /**
     * Get route arguments
     *
     * @return string[]
     */
    public function getArguments(): array;

    /**
     * Set a route arguments
     *
     * @param array $arguments
     *
     * @return self
     */
    public function addArguments(array $arguments): RouteInterface;

    /**
     * Set a list of regular expression requirements on the route.
     *
     * @see addPattern() method
     *
     * @param array $wheres
     *
     * @return $this
     */
    public function whereArray(array $wheres = []): RouteInterface;

    /**
     * Returns the lowercased schemes this route is restricted to.
     * So a null return means that any scheme is allowed.
     *
     * @return string[]|null The schemes
     */
    public function getSchemes(): ?array;

    /**
     * Sets the schemes (e.g. 'https') this route is restricted to.
     * So an empty array means that any scheme is allowed.
     *
     * @param string|string[] $schemes The scheme or an array of schemes
     *
     * @return $this
     */
    public function addSchemes($schemes): RouteInterface;

    /**
     * Gets a default value.
     *
     * @param string $name
     * @param string|null $default
     *
     * @return string|null The default value or defaults when not given
     */
    public function getDefault(string $name, ?string $default = null): ?string;

    /**
     * Adds defaults.
     *
     * @param array $defaults The defaults
     *
     * @return RouteInterface
     */
    public function addDefaults(array $defaults): RouteInterface;

    /**
     * Get route default options
     *
     * @return array
     */
    public function getDefaults(): array;

    /**
     * Checks if a default value is set for the given variable.
     *
     * @param string $name A variable name
     *
     * @return bool true if the default value is set, false otherwise
     */
    public function hasDefault(string $name): bool;

    /**
     * Checks if route exists in a group
     *
     * @return bool
     */
    public function hasGroup(): bool;

    /**
     * The group id, route belongs to.
     *
     * @return string|null
     */
    public function getGroupId(): ?string;

    /**
     * Add middlewares to route.
     *
     * @param MiddlewareInterface|string|array|callable|RequestHandlerInterface $middleware
     * @return RouteInterface
     */
    public function addMiddleware($middleware): RouteInterface;

    /**
     * Get middlewares from stack.
     *
     * @return array
     */
    public function getMiddlewares(): array;
}
