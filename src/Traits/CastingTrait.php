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

trait CastingTrait
{
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
        $this->__unserialize(\unserialize($serialized));
    }

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
        // Match domain + scheme from pattern...
        if (false !== \preg_match($regex = '@^(?:(https?):)?(//[^/]+)@i', $route)) {
            $route = $this->castDomain($route, $regex);
        }

        if (false !== \strpbrk($route, '*') && false !== \preg_match(self::RCA_PATTERN, $route, $matches)) {
            if (!isset($matches['route']) || empty($matches['route'])) {
                throw new InvalidControllerException("Unable to locate route candidate on `{$route}`");
            }

            if (isset($matches['controller'], $matches['action'])) {
                $this->controller = [$matches['controller'] ?: $this->controller, $matches['action']];
            }

            $route = $matches['route'];
        }

        return (empty($route) || '/' === $route) ? '/' : $route;
    }

    /**
     * Match scheme and domain from route patterned path
     *
     * @param string $route
     * @param string $regex
     *
     * @return string
     */
    private function castDomain(string $route, string $regex): string
    {
        return (string) \preg_replace_callback($regex, function (array $matches): string {
            $this->setDomain(isset($matches[1]) ? $matches[0] : $matches[2]);

            return '';
        }, $route);
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
        if (\in_array($uri, ['', '/'], true)) {
            return \rtrim($prefix, '/') . $uri;
        }

        if (1 === \preg_match('/^([^\|\/|&|-|_|~|@]+)(&|-|_|~|@)/i', $prefix, $matches)) {
            $newPattern = \rtrim($prefix, $matches[2]) . $matches[2] . $uri;
        }

        return !empty($newPattern) ? $newPattern : \rtrim($prefix, '/') . '/' . \ltrim($uri, '/');
    }
}
