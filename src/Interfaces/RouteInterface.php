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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
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
     * @param array|string $name
     * @param string       $expression
     *
     * @return $this
     */
    public function setPattern(string $name, string $expression = null): RouteInterface;

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
    public function setDomain(?string $domain = null): RouteInterface;

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
     * Set a list of regular expression requirements on the route.
     *
     * @param array $wheres
     *
     * @return $this
     */
    public function whereArray(array $wheres = []): RouteInterface;

    /**
     * Set a route argument
     *
     * @param string $name
     * @param string $value
     * @param bool $includeInSavedArguments
     *
     * @return self
     */
    public function setArgument(string $name, ?string $value, bool $includeInSavedArguments = true): RouteInterface;

    /**
     * Replace route arguments
     *
     * @param string[] $arguments
     *
     * @return self
     */
    public function setArguments(array $arguments): RouteInterface;

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
     * This method implements a fluent interface.
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
     * Prepare the route for use
     *
     * @param array $arguments
     * @return RouteInterface
     */
    public function prepare(array $arguments): RouteInterface;

    /**
     * Run route controller
     *
     * This method traverses the middleware stack, including the route's callable
     * and captures the resultant HTTP response object. It then sends the response
     * back to the Application.
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function run(ServerRequestInterface $request): ResponseInterface;
}
