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
        if (!(\strpbrk($route, ':*{') || '/' === @$route[1] ?? '')) {
            return '' === $route ? '/' : $route;
        }

        $pattern = \preg_replace_callback(Route::RCA_PATTERN, function (array $matches): string {
            if (isset($matches[1])) {
                $this->schemes[$matches[1]] = true;
            }

            if (isset($matches[2])) {
                $this->domain[] = $matches[2];
            }

            // Match controller from route pattern.
            $handler = $matches[4] ?? $this->controller;

            if (isset($matches[5])) {
                $this->controller = !empty($handler) ? [$handler, $matches[5]] : $matches[5];
            }

            return $matches[3];
        }, $route, -1, $count, \PREG_UNMATCHED_AS_NULL);

        return $pattern ?? $route;
    }

    /**
     * @internal skip throwing an exception and return exisitng $controller
     *
     * @param callable|object|string|string[] $controller
     *
     * @throws InvalidControllerException if $namespace is invalid
     *
     * @return mixed
     */
    private function castNamespace(string $namespace, $controller)
    {
        if ('\\' !== $namespace[-1]) {
            throw new InvalidControllerException(\sprintf('Namespace "%s" provided for routes must end with a "\\".', $namespace));
        }

        if ($controller instanceof ResourceHandler) {
            return $controller->namespace($namespace);
        }

        if ((\is_string($controller) && !\class_exists($controller)) && !\str_starts_with($controller, $namespace)) {
            return $namespace . \ltrim($controller, '\\/');
        }

        if (\is_array($controller) && (!\is_object($controller[0]) && !\class_exists($controller[0]))) {
            $controller[0] = $namespace . \ltrim($controller[0], '\\/');

            return $controller;
        }

        return $controller;
    }
        }

    }

    /**
     * Match scheme and domain from route patterned path
     *
     * @param array<int|string,null|string> $matches
     */
    private function castDomain(array $matches): string
    {
        $domain = $matches['host'] ?? '';
        $route  = $matches['route'] ?? '';

        if ('api' === $scheme = $matches['scheme'] ?? '') {
            $this->defaults['_api'] = \ucfirst($domain);

            return $route;
        }

        if (
            (empty($route) || '/' === $route || 0 === preg_match('/.\w+$/', $domain)) &&
            empty($matches[2])
        ) {
            return $domain . $route;
        }

        if (!empty($domain)) {
            if (!empty($scheme)) {
                $this->schemes[$scheme] = true;
            }

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
