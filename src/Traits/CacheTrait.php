<?php

declare(strict_types=1);

/*
 * This file is part of Flight Routing.
 *
 * PHP version 8.0 and above required
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 Divine Niiquaye Ibok (https://divinenii.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flight\Routing\Traits;

use Flight\Routing\Handlers\ResourceHandler;
use Flight\Routing\RouteCollection;

/**
 * A default cache implementation for route match.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
trait CacheTrait
{
    private ?string $cache = null;

    /**
     * @param string $path file path to store compiled routes
     */
    public function setCache(string $path): void
    {
        $this->cache = $path;
    }

    /**
     * A well php value formatter, better than (var_export).
     */
    public static function export(mixed $value, string $indent = ''): string
    {
        switch (true) {
            case [] === $value:
                return '[]';
            case \is_array($value):
                $j = -1;
                $code = ($t = \count($value, \COUNT_RECURSIVE)) > 15 ? "[\n" : '[';
                $subIndent = $t > 15 ? $indent.'  ' : $indent = '';

                foreach ($value as $k => $v) {
                    $code .= $subIndent;

                    if (!\is_int($k) || $k !== ++$j) {
                        $code .= self::export($k, $subIndent).' => ';
                    }

                    $code .= self::export($v, $subIndent).($t > 15 ? ",\n" : ', ');
                }

                return \rtrim($code, ', ').$indent.']';
            case $value instanceof ResourceHandler:
                return $value::class.'('.self::export($value(''), $indent).')';
            case $value instanceof \stdClass:
                return '(object) '.self::export((array) $value, $indent);
            case $value instanceof RouteCollection:
                return $value::class.'::__set_state('.self::export([
                    'routes' => $value->getRoutes(),
                    'defaultIndex' => $value->count() - 1,
                    'sorted' => true,
                ], $indent).')';
            case \is_object($value):
                if (\method_exists($value, '__set_state')) {
                    return $value::class.'::__set_state('.self::export(
                        \array_merge(...\array_map(function (\ReflectionProperty $v) use ($value): array {
                            $v->setAccessible(true);

                            return [$v->getName() => $v->getValue($value)];
                        }, (new \ReflectionObject($value))->getProperties()))
                    );
                }

                return 'unserialize(\''.\serialize($value).'\')';
        }

        return \var_export($value, true);
    }

    protected function doCache(): RouteCollection
    {
        if (\is_array($a = @include $this->cache)) {
            $this->optimized = $a;

            return $this->optimized[2] ??= $this->collection ?? new RouteCollection();
        }

        if (\is_callable($collection = $this->collection ?? new RouteCollection())) {
            $collection($collection = new RouteCollection());
            $collection->sort();
            $doCache = true;
        }

        if (!\is_dir($directory = \dirname($this->cache))) {
            @\mkdir($directory, 0775, true);
        }

        try {
            return $collection;
        } finally {
            $dumpData = $this->buildCache($collection, $doCache ?? false);
            \file_put_contents($this->cache, "<?php // auto generated: AVOID MODIFYING\n\nreturn ".$dumpData.";\n");

            if (\function_exists('opcache_invalidate') && \filter_var(\ini_get('opcache.enable'), \FILTER_VALIDATE_BOOLEAN)) {
                @\opcache_invalidate($this->cache, true);
            }
        }
    }

    protected function buildCache(RouteCollection $collection, bool $doCache): string
    {
        $dynamicRoutes = [];
        $this->optimized = [[], [[], []]];

        foreach ($collection->getRoutes() as $i => $route) {
            $trimmed = \preg_replace('/\W$/', '', $path = $route['path']);

            if (\in_array($prefix = $route['prefix'] ?? '/', [$trimmed, $path], true)) {
                $this->optimized[0][$trimmed ?: '/'][] = $i;
                continue;
            }
            [$path, $var] = $this->getCompiler()->compile($path, $route['placeholders'] ?? []);
            $path = \str_replace('\/', '/', \substr($path, 1 + \strpos($path, '^'), -(\strlen($path) - \strrpos($path, '$'))));

            if (($l = \array_key_last($dynamicRoutes)) && !\in_array($l, ['/', $prefix], true)) {
                for ($o = 0, $new = ''; $o < \strlen($prefix); ++$o) {
                    if ($prefix[$o] !== ($l[$o] ?? null)) {
                        break;
                    }
                    $new .= $l[$o];
                }

                if ($new && '/' !== $new) {
                    if ($l !== $new) {
                        $dynamicRoutes[$new] = $dynamicRoutes[$l];
                        unset($dynamicRoutes[$l]);
                    }
                    $prefix = $new;
                }
            }
            $dynamicRoutes[$prefix][] = \preg_replace('#\?(?|P<\w+>|<\w+>|\'\w+\')#', '', $path)."(*:{$i})";
            $this->optimized[1][1][$i] = $var;
        }
        \ksort($this->optimized[0], \SORT_NATURAL);
        \uksort($dynamicRoutes, fn (string $a, string $b): int => \in_array('/', [$a, $b], true) ? \strcmp($b, $a) : \strcmp($a, $b));

        foreach ($dynamicRoutes as $offset => $paths) {
            $numParts = \max(1, \round(($c = \count($paths)) / 30));

            foreach (\array_chunk($paths, (int) \ceil($c / $numParts)) as $chunk) {
                $this->optimized[1][0]['/'.\ltrim($offset, '/')][] = '~^(?|'.\implode('|', $chunk).')$~';
            }
        }

        return self::export([...$this->optimized, $doCache ? $collection : null]);
    }
}
