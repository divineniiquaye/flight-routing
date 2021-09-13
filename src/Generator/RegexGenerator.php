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

namespace Flight\Routing\Generator;

use Flight\Routing\Routes\FastRoute;
use Flight\Routing\Interfaces\RouteCompilerInterface;

/**
 * A helper Prefix tree class to help help in the compilation of routes in
 * preserving routes order as a full regex excluding modifies.
 *
 * This class is retrieved from symfony's routing component to add
 * high performance into Flight Routing and avoid requiring the whole component.
 *
 * @author Frank de Jonge <info@frankdejonge.nl>
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 *
 * @internal
 */
class RegexGenerator
{
    /** @var string */
    private $prefix;

    /** @var string[] */
    private $staticPrefixes = [];

    /** @var string[] */
    private $prefixes = [];

    /** @var array[]|self[] */
    private $items = [];

    public function __construct(string $prefix = '/')
    {
        $this->prefix = $prefix;
    }

    /**
     * This method uses default routes compiler.
     *
     * @param array<int,FastRoute> $routes
     *
     * @return array<int,mixed>
     */
    public static function beforeCaching(RouteCompilerInterface $compiler, array $routes): array
    {
        $tree = new static();
        $indexedRoutes = [];

        for ($i = 0; $i < \count($routes); ++$i) {
            [$pathRegex, $hostsRegex, $variables] = $compiler->compile($route = $routes[$i]);
            $pathRegex = \preg_replace('/\?(?|P<\w+>|<\w+>|\'\w+\')/', '', $pathRegex);

            $tree->addRoute($pathRegex, [$pathRegex, $i, [$route, $hostsRegex, $variables]]);
        }

        $compiledRegex = '~^(?' . $tree->compile(0, $indexedRoutes) . ')$~u';
        \ksort($indexedRoutes);

        return [$compiledRegex, $indexedRoutes, $compiler];
    }

    /**
     * Compiles a regexp tree of sub-patterns that matches nested same-prefix routes.
     *
     * The route item should contain:
     * - pathRegex
     * - an id used for (*:MARK)
     * - an array of additional/optional values if maybe required.
     */
    public function compile(int $prefixLen, array &$variables = []): string
    {
        $code = '';

        foreach ($this->items as $route) {
            if ($route instanceof self) {
                $prefix = \substr($route->prefix, $prefixLen);
                $code .= '|' . \ltrim($prefix, '?') . '(?' . $route->compile($prefixLen + \strlen($prefix), $variables) . ')';

                continue;
            }

            $code .= '|' . \substr($route[0], $prefixLen) . '(*:' . $route[1] . ')';
            $variables[$route[1]] = $route[2];
        }

        return $code;
    }

    /**
     * Adds a route to a group.
     *
     * @param array|self $route
     */
    public function addRoute(string $prefix, $route): void
    {
        [$prefix, $staticPrefix] = $this->getCommonPrefix($prefix, $prefix);

        for ($i = \count($this->items) - 1; 0 <= $i; --$i) {
            $item = $this->items[$i];

            [$commonPrefix, $commonStaticPrefix] = $this->getCommonPrefix($prefix, $this->prefixes[$i]);

            if ($this->prefix === $commonPrefix) {
                // the new route and a previous one have no common prefix, let's see if they are exclusive to each others

                if ($this->prefix !== $staticPrefix && $this->prefix !== $this->staticPrefixes[$i]) {
                    // the new route and the previous one have exclusive static prefixes
                    continue;
                }

                if ($this->prefix === $staticPrefix && $this->prefix === $this->staticPrefixes[$i]) {
                    // the new route and the previous one have no static prefix
                    break;
                }

                if ($this->prefixes[$i] !== $this->staticPrefixes[$i] && $this->prefix === $this->staticPrefixes[$i]) {
                    // the previous route is non-static and has no static prefix
                    break;
                }

                if ($prefix !== $staticPrefix && $this->prefix === $staticPrefix) {
                    // the new route is non-static and has no static prefix
                    break;
                }

                continue;
            }

            if ($item instanceof self && $this->prefixes[$i] === $commonPrefix) {
                // the new route is a child of a previous one, let's nest it
                $item->addRoute($prefix, $route);
            } else {
                // the new route and a previous one have a common prefix, let's merge them
                $child = new self($commonPrefix);
                [$child->prefixes[0], $child->staticPrefixes[0]] = $child->getCommonPrefix($this->prefixes[$i], $this->prefixes[$i]);
                [$child->prefixes[1], $child->staticPrefixes[1]] = $child->getCommonPrefix($prefix, $prefix);
                $child->items = [$this->items[$i], $route];

                $this->staticPrefixes[$i] = $commonStaticPrefix;
                $this->prefixes[$i] = $commonPrefix;
                $this->items[$i] = $child;
            }

            return;
        }

        // No optimised case was found, in this case we simple add the route for possible
        // grouping when new routes are added.
        $this->staticPrefixes[] = $staticPrefix;
        $this->prefixes[] = $prefix;
        $this->items[] = $route;
    }

    public static function handleError(int $type, string $msg): bool
    {
        return false !== \strpos($msg, 'Compilation failed: lookbehind assertion is not fixed length');
    }

    /**
     * Gets the full and static common prefixes between two route patterns.
     *
     * The static prefix stops at last at the first opening bracket.
     */
    private function getCommonPrefix(string $prefix, string $anotherPrefix): array
    {
        $baseLength = \strlen($this->prefix);
        $end = \min(\strlen($prefix), \strlen($anotherPrefix));
        $staticLength = null;
        \set_error_handler([__CLASS__, 'handleError']);

        for ($i = $baseLength; $i < $end && $prefix[$i] === $anotherPrefix[$i]; ++$i) {
            if ('(' === $prefix[$i]) {
                $staticLength = $staticLength ?? $i;

                for ($j = 1 + $i, $n = 1; $j < $end && 0 < $n; ++$j) {
                    if ($prefix[$j] !== $anotherPrefix[$j]) {
                        break 2;
                    }

                    if ('(' === $prefix[$j]) {
                        ++$n;
                    } elseif (')' === $prefix[$j]) {
                        --$n;
                    } elseif ('\\' === $prefix[$j] && (++$j === $end || $prefix[$j] !== $anotherPrefix[$j])) {
                        --$j;

                        break;
                    }
                }

                if (0 < $n) {
                    break;
                }

                if (('?' === ($prefix[$j] ?? '') || '?' === ($anotherPrefix[$j] ?? '')) && ($prefix[$j] ?? '') !== ($anotherPrefix[$j] ?? '')) {
                    break;
                }
                $subPattern = \substr($prefix, $i, $j - $i);

                if ($prefix !== $anotherPrefix && !\preg_match('/^\(\[[^\]]++\]\+\+\)$/', $subPattern) && !\preg_match('{(?<!' . $subPattern . ')}', '')) {
                    // sub-patterns of variable length are not considered as common prefixes because their greediness would break in-order matching
                    break;
                }
                $i = $j - 1;
            } elseif ('\\' === $prefix[$i] && (++$i === $end || $prefix[$i] !== $anotherPrefix[$i])) {
                --$i;

                break;
            }
        }

        \restore_error_handler();

        if ($i < $end && 0b10 === (\ord($prefix[$i]) >> 6) && \preg_match('//u', $prefix . ' ' . $anotherPrefix)) {
            do {
                // Prevent cutting in the middle of an UTF-8 characters
                --$i;
            } while (0b10 === (\ord($prefix[$i]) >> 6));
        }

        return [\substr($prefix, 0, $i), \substr($prefix, 0, $staticLength ?? $i)];
    }
}
