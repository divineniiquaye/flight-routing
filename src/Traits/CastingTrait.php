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
     */
    private function castRoute(string $route): string
    {
        $urlRegex = \strtr(Route::URL_PATTERN, ['/^' => '/^(?:', '$/u' => ')']);
        $urlRegex .= \str_replace('/^', '?', Route::RCA_PATTERN);

        // Match url + rca from pattern...
        \preg_match($urlRegex, $route, $matches);

        if (empty($matches)) {
            return $route;
        }

        if (isset($matches['c'], $matches['a'])) {
            $handler          = $matches['c'] ?: $this->controller;
            $this->controller = !$handler ? $matches['a'] : [$handler, $matches['a']];
        }

        if (isset($matches['host'])) {
            $route = $this->castDomain($matches);
        }

        return $route ?: '/';
    }

    /**
     * Match scheme and domain from route patterned path
     *
     * @param array<int|string,null|string> $matches
     */
    private function castDomain(array $matches): string
    {
        $domain = $matches['host'] ?? '';
        $scheme = $matches['scheme'] ?? '';
        $route  = $matches['route'] ?? '';

        if (
            (empty($route) || '/' === $route || 0 === preg_match('/.\w+$/', $domain)) &&
            (!empty($domain) && empty($matches[2]))
        ) {
            $route  = $domain . $route;
            $domain = '';
        }

        if ('api' === $scheme && !empty($domain)) {
            $this->defaults['_api'] = \ucfirst($domain);

            return $route;
        } elseif (!empty($scheme) && 'api' !== $scheme) {
            $this->schemes[$scheme] = true;
        }

        if (!empty($domain) && 'api' !== $scheme) {
            $this->domain[$domain] = true;
        }

        return $route;
    }

    /**
     * Ensures that the right-most slash is trimmed for prefixes of more than
     * one character, and that the prefix begins with a slash.
     */
    private function castPrefix(string $uri, string $prefix): string
    {
        // Allow homepage uri on prefix just like python django url style.
        if (empty($uri) || '/' === $uri) {
            return \rtrim($prefix, '/') . $uri;
        }

        if (1 === \preg_match('/^(.*)(\:|\-|\_|\~|\@)$/', $prefix, $matches)) {
            if ($matches[2] !== $uri[0]) {
                $uri = $matches[2] . $uri;
            }

            return \rtrim($prefix, $matches[2]) . $uri;
        }

        return \rtrim($prefix, '/') . '/' . \ltrim($uri, '/');
    }
}
