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

namespace Flight\Routing\Traits;

use Flight\Routing\Exceptions\InvalidControllerException;

trait PathsTrait
{
    /**
     * Route path pattern
     *
     * @var string
     */
    protected $path;

    /**
     * Route path prefix
     *
     * @var string
     */
    protected $prefix;

    /**
     * {@inheritdoc}
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Sets the pattern for the path.
     *
     * @param string $pattern The path pattern
     *
     * @return void
     */
    protected function setPath(string $pattern): void
    {
        if (null !== $this->prefix) {
            $pattern = $this->normalizePrefix($pattern, $this->prefix);
        }

        // Match domain + scheme from pattern...
        if (preg_match('@^(?:(https?):)?(//[^/]+)@i', $pattern)) {
            $pattern = preg_replace_callback('@^(?:(https?):)?(//[^/]+)@i', function ($matches) {
                $this->addDomain(isset($matches[1]) ? $matches[0] : $matches[2]);

                return '';
            }, $pattern);
        }

        //In some cases route controller can be provided as *<controller@action> pair, we can try to
        //generate such route automatically.
        $pattern = $this->castRoute($pattern);

        $this->path = (empty($pattern) || '/' === $pattern) ? '/' : $pattern;
    }

    /**
     * Locates appropriate route by name. Support dynamic route allocation using following pattern:
     * Pattern route:   `pattern/*<controller@action>`
     * Default route: `*<controller@action>`
     * Only action:   `pattern/*<action>`
     *
     * @param string $route
     * @return string
     *
     * @throws InvalidControllerException
     */
    protected function castRoute(string $route): ?string
    {
        if (
            strpbrk($route, '*') !== false &&
            preg_match(
                '/^(?:(?P<route>[^(.*)]+)\*<)?(?:(?P<controller>[^@]+)@+)?(?P<action>[a-z_\-]+)\>$/i',
                $route,
                $matches
            )
        ) {
            if (!isset($matches['route'])) {
                throw new InvalidControllerException("Unable to locate route candidate for `{$route}`");
            }

            if (isset($matches['controller'], $matches['action'])) {
                $this->setController([$matches['controller'] ?: $this->controller, $matches['action']]);
            }

            return $matches['route'];
        }

        return $route;
    }

    /**
     * Ensures that the right-most slash is trimmed for prefixes of more than
     * one character, and that the prefix begins with a slash.
     *
     * @param string $uri
     * @param mixed $prefix
     *
     * @return string
     */
    private function normalizePrefix(string $uri, $prefix)
    {
        // Allow homepage uri on prefix just like python dgango url style.
        if (in_array($uri, ['', '/'], true)) {
            return rtrim($prefix, '/') . $uri;
        }

        if (preg_match('/^([^\|\/|&|-|_|~|@]+)(&|-|_|~|@)/i', $prefix, $matches)) {
            $newPattern = rtrim($prefix, $matches[2]) . $matches[2] . $uri;
        }

        return !empty($newPattern) ? $newPattern : rtrim($prefix, '/') . '/' . ltrim($uri, '/');
    }
}
