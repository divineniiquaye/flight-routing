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

namespace Flight\Routing\Interfaces;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionException;

interface RouteInterface
{
    /**
     * Get route methods.
     *
     * @return string[]
     */
    public function getMethods(): array;

    /**
     * Get the route patterned path.
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
    public function addPattern(string $name, string $expression): self;

    /**
     * Get route requirements.
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
     * @param null|string $domain
     *
     * @return RouteInterface
     */
    public function addDomain(?string $domain): self;

    /**
     * Get route name.
     *
     * @return null|string
     */
    public function getName(): ?string;

    /**
     * Set route name.
     *
     * @param null|string $name
     *
     * @return static
     */
    public function setName(?string $name): self;

    /**
     * Retrieve a specific route argument.
     *
     * @param string      $name
     * @param null|string $default
     *
     * @return null|string
     */
    public function getArgument(string $name, ?string $default = null): ?string;

    /**
     * Get route arguments.
     *
     * @return array<string,mixed>
     */
    public function getArguments(): array;

    /**
     * Set a route arguments.
     *
     * @param array<string,mixed> $arguments
     *
     * @return self
     */
    public function addArguments(array $arguments): self;

    /**
     * Set a list of regular expression requirements on the route.
     *
     * @see addPattern() method
     *
     * @param array<string,string> $wheres
     *
     * @return $this
     */
    public function whereArray(array $wheres = []): self;

    /**
     * Returns the lowercased schemes this route is restricted to.
     * So a null return means that any scheme is allowed.
     *
     * @return null|string[] The schemes
     */
    public function getSchemes(): ?array;

    /**
     * Sets the schemes (e.g. 'https') this route is restricted to.
     * So an empty array means that any scheme is allowed.
     *
     * @param null|string|string[] $schemes The scheme or an array of schemes
     *
     * @return $this
     */
    public function addSchemes($schemes): self;

    /**
     * Gets a default value.
     *
     * @param string      $name
     * @param null|string $default
     *
     * @return null|string The default value or defaults when not given
     */
    public function getDefault(string $name, ?string $default = null): ?string;

    /**
     * Adds defaults.
     *
     * @param array<string,mixed> $defaults The defaults
     *
     * @return RouteInterface
     */
    public function addDefaults(array $defaults): self;

    /**
     * Get route default options.
     *
     * @return array<string,mixed>
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
     * Checks if route exists in a group.
     *
     * @return bool
     */
    public function hasGroup(): bool;

    /**
     * The group id, route belongs to.
     *
     * @return null|string
     */
    public function getGroupId(): ?string;

    /**
     * Add middlewares to route.
     *
     * @param callable|MiddlewareInterface|RequestHandlerInterface|string|string[] $middleware
     *
     * @return RouteInterface
     */
    public function addMiddleware($middleware): self;

    /**
     * Get middlewares from stack.
     *
     * @return string[]
     */
    public function getMiddlewares(): array;

    /**
     * Handles a callable controller served on a route.
     *
     * @param callable                  $controller
     * @param CallableResolverInterface $callableResolver
     *
     * @throws ReflectionException
     *
     * @return callable
     */
    public function handle(callable $controller, CallableResolverInterface $callableResolver): callable;
}
