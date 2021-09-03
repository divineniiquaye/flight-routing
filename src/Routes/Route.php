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

namespace Flight\Routing\Routes;

use Flight\Routing\Exceptions\UriHandlerException;

/**
 * Value object representing a single route.
 *
 * The default support for this route class includes DomainRoute support:
 * - domain and schemes and handler casting from route pattern
 * - resolves route pattern prefixing
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class Route extends DomainRoute
{
    /**
     * A Pattern to Locates appropriate route by name, support dynamic route allocation using following pattern:
     * Pattern route:   `pattern/*<controller@action>`
     * Default route: `*<controller@action>`
     * Only action:   `pattern/*<action>`.
     *
     * @var string
     */
    public const RCA_PATTERN = '#^(?:([a-z]+)\:)?(?:\/{2}([^\/]+))?([^*]*)(?:\*\<(?:([\w+\\\\]+)\@)?(\w+)\>)?$#u';

    /**
     * Slashes supported on browser when used.
     *
     * @var string[]
     */
    public const URL_PREFIX_SLASHES = ['/' => '/', ':' => ':', '-' => '-', '_' => '_', '~' => '~', '@' => '@'];

    /**
     * {@inheritdoc}
     */
    public static function to(string $pattern, $methods = self::DEFAULT_METHODS, $handler = null): self
    {
        return parent::to($pattern, $methods, $handler);
    }

    /**
     * {@inheritdoc}
     */
    public function prefix(string $path): self
    {
        $this->data['path'] = self::resolvePrefix($this->data['path'], $path);

        return $this;
    }

    /**
     * Locates appropriate route by name. Support dynamic route allocation using following pattern:
     * Pattern route:   `pattern/*<controller@action>`
     * Default route: `*<controller@action>`
     * Only action:   `pattern/*<action>`.
     */
    protected function resolvePattern(string $pattern): string
    {
        \preg_match(self::RCA_PATTERN, $pattern, $matches, \PREG_UNMATCHED_AS_NULL);

        if (!empty($matches)) {
            if (isset($matches[1])) {
                $this->data['schemes'][] = $matches[1];
            }

            if (isset($matches[2])) {
                $this->data['hosts'][] = $matches[2];
            }

            if (isset($matches[5])) {
                // Match controller from route pattern.
                $handler = $matches[4] ?? $this->data['handler'] ?? null;
                $this->data['handler'] = !empty($handler) ? [$handler, $matches[5]] : $matches[5];
            }

            if (empty($matches[3] ?? '')) {
                throw new UriHandlerException(\sprintf('The route pattern "%s" is invalid as route path must be present in pattern.', $pattern));
            }
        }

        return $matches[3] ?? $pattern ?: '/';
    }

    /**
     * Ensures that the right-most slash is trimmed for prefixes of more than
     * one character, and that the prefix begins with a slash.
     */
    private static function resolvePrefix(string $uri, string $prefix): string
    {
        // This is not accepted, but we're just avoiding throwing an exception ...
        if (empty($prefix)) {
            return $uri;
        }

        if (isset(self::URL_PREFIX_SLASHES[$prefix[-1]], self::URL_PREFIX_SLASHES[$uri[0]])) {
            return $prefix . \ltrim($uri, \implode('', self::URL_PREFIX_SLASHES));
        }

        // browser supported slashes ...
        $slashExist = self::URL_PREFIX_SLASHES[$prefix[-1]] ?? self::URL_PREFIX_SLASHES[$uri[0]] ?? null;

        if (null === $slashExist) {
            $prefix .= '/';
        }

        return $prefix . $uri;
    }
}
