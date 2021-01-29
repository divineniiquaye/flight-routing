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

use Flight\Routing\Exceptions\InvalidControllerException;
use Flight\Routing\Route;

trait CastingTrait
{
    /** @var null|string */
    private $name;

    /** @var string */
    private $path;

    /** @var array<string,bool> */
    private $methods = [];

    /** @var array<string,bool> */
    private $domain = [];

    /** @var array<string,bool> */
    private $schemes = [];

    /** @var array<string,mixed> */
    private $defaults = [];

    /** @var array<string,string|string[]> */
    private $patterns = [];

    /** @var array<int,mixed> */
    private $middlewares = [];

    /** @var mixed */
    private $controller;

    /**
     * Locates appropriate route by name. Support dynamic route allocation using following pattern:
     * Pattern route:   `pattern/*<controller@action>`
     * Default route: `*<controller@action>`
     * Only action:   `pattern/*<action>`.
     *
     * @param string $route
     *
     * @throws InvalidControllerException
     *
     * @return string
     */
    private function castRoute(string $route): string
    {
        $urlRegex = \strtr(Route::URL_PATTERN, ['/^' => '/^(?:', '$/u' => ')']);

        // Match url + rca from pattern...
        \preg_match($urlRegex . \strtr(Route::RCA_PATTERN, ['/^' => '?']), $route, $matches);

        if (empty($matches)) {
            return $route;
        }

        if (isset($matches['c'], $matches['a'])) {
            $handler          = $matches['c'] ?: $this->controller;
            $this->controller = !$handler ? $matches['a'] : [$handler, $matches['a']];
        }

        if (isset($matches['domain'])) {
            $route = $this->castDomain($matches, $route);
        }

        return $route;
    }

    /**
     * Match scheme and domain from route patterned path
     *
     * @param array<int|string,null|string> $matches
     * @param string                        $route
     *
     * @return string
     */
    private function castDomain(array $matches, string $route): string
    {
        $domain = $matches['domain'] ?? null;

        if (isset($matches['scheme']) && !empty($matches['scheme'])) {
            $this->schemes[$matches['scheme']] = true;
        }

        if ((!isset($matches[4]) || empty($matches[4])) && false === \strpos($domain ?? '', '//')) {
            $matches['route'] = $domain . ($matches['route'] ?? null);
            $domain           = null;
        }

        if (!empty($domain)) {
            $this->domain[$matches['host']] = true;
        }

        if (!isset($matches['route']) || empty($matches['route'])) {
            throw new InvalidControllerException("Unable to locate route candidate on `{$route}`");
        }

        return $matches['route'];
    }

    /**
     * Ensures that the right-most slash is trimmed for prefixes of more than
     * one character, and that the prefix begins with a slash.
     *
     * @param string $uri
     * @param string $prefix
     *
     * @return string
     */
    private function castPrefix(string $uri, string $prefix): string
    {
        // Allow homepage uri on prefix just like python django url style.
        if (empty($uri) || '/' === $uri) {
            return \rtrim($prefix, '/') . $uri;
        }

        if (1 === \preg_match('/^([^\/&\-_~\|@]+)(&|-|_|~|@)$/', $prefix, $matches)) {
            return \rtrim($prefix, $matches[2]) . $matches[2] . $uri;
        }

        return \rtrim($prefix, '/') . '/' . \ltrim($uri, '/');
    }
}
