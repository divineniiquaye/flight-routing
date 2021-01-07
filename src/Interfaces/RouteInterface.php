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

use Fig\Http\Message\RequestMethodInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

interface RouteInterface extends RequestMethodInterface
{
    /**
     * Standard HTTP methods against which to test HEAD/OPTIONS requests.
     */
    public const HTTP_METHODS_STANDARD = [
        self::METHOD_HEAD,
        self::METHOD_GET,
        self::METHOD_POST,
        self::METHOD_PUT,
        self::METHOD_PATCH,
        self::METHOD_DELETE,
        self::METHOD_PURGE,
        self::METHOD_OPTIONS,
        self::METHOD_TRACE,
        self::METHOD_CONNECT,
    ];

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
     * @return array<mixed,string>|callable|string
     */
    public function getController();

    /**
     * Get route requirements.
     *
     * @return array<string,string|string[]>
     */
    public function getPatterns(): array;

    /**
     * Get route domain, same as host.
     *
     * @return string
     */
    public function getDomain(): string;

    /**
     * Get route name.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get route arguments.
     *
     * @return array<string,mixed>
     */
    public function getArguments(): array;

    /**
     * Get route default options.
     *
     * @return array<string,mixed>
     */
    public function getDefaults(): array;

    /**
     * Get middlewares from stack.
     *
     * @return array<int,callable|MiddlewareInterface|RequestHandlerInterface|string>
     */
    public function getMiddlewares(): array;

    /**
     * Returns the lower cased schemes this route is restricted to.
     *
     * @return string[]
     */
    public function getSchemes(): array;

    /**
     * Adds the given domain scheme(s) to the route
     *
     * @param string ...$schemes
     *
     * @return RouteInterface
     */
    public function setScheme(string ...$schemes): self;

    /**
     * Set route name.
     *
     * @param string $name
     *
     * @return RouteInterface
     */
    public function setName(string $name): self;

    /**
     * Adds defaults.
     *
     * @param array<string,mixed> $defaults The defaults
     *
     * @return RouteInterface
     */
    public function setDefaults(array $defaults): self;

    /**
     * Set the domain for the route.
     *
     * @param string $domain
     *
     * @return RouteInterface
     */
    public function setDomain(string $domain): self;

    /**
     * Set a route controller's arguments.
     *
     * @param array<int|string,mixed> $arguments
     *
     * @return RouteInterface
     */
    public function setArguments(array $arguments): self;

    /**
     * Set a list of regular expression requirements on the route.
     *
     * @see addPattern() method
     *
     * @param array<string,string> $patterns
     *
     * @return RouteInterface
     */
    public function setPatterns(array $patterns): self;

    /**
     * Add middleware(s) to route.
     *
     * NB: Adding a request handler as middleware ends the middlewares cycle.
     *
     * @param callable|MiddlewareInterface|RequestHandlerInterface|string ...$middlewares
     *
     * @return RouteInterface
     */
    public function addMiddleware(...$middlewares): self;

    /**
     * Set a regular expression requirement on the route.
     *
     * @param string $name
     * @param string|string[] $expression
     *
     * @return RouteInterface
     */
    public function addPattern(string $name, $expression): self;

    /**
     * Adds the given prefix to the route path
     *
     * @param string $prefix
     *
     * @return RouteInterface
     */
    public function addPrefix(string $prefix): self;

    /**
     * Adds the given method(s) to the route
     *
     * @param string ...$methods
     *
     * @return RouteInterface
     */
    public function addMethod(string ...$methods): RouteInterface;

    /**
     * @internal Used to see a new handler, when matched.
     *
     * @param mixed $handler
     */
    public function setController($handler): void;
}
