<?php declare(strict_types=1);

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

use Flight\Routing\Exceptions\{MethodNotAllowedException, UriHandlerException};
use Psr\Http\Message\UriInterface;

/**
 * The default implementation for route match.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
trait ResolverTrait
{
    /** @var array<int|string,mixed> */
    private array $optimized = [];

    /**
     * @param array<string,mixed>                  $route
     * @param array<int,array<string,bool|string>> $errors
     */
    protected function assertRoute(string $method, UriInterface $uri, array &$route, array &$errors): bool
    {
        $matched = true;

        if (!isset($route['methods'][$method])) {
            $errors[0] += $route['methods'] ?? [];
            $matched = false;
        } elseif (isset($route['hosts']) && !isset($route['hosts'][$errors[2] ??= \rtrim($uri->getHost().':'.$uri->getPort(), ':')])) {
            $hosts = \array_keys($route['hosts'], true, true);
            [$hostsRegex, $hostVar] = $this->compiler->compile(\implode('|', $hosts), $route['placeholders'] ?? []);

            if ($matched = 1 === \preg_match($hostsRegex.'i', $errors[2], $matches, \PREG_UNMATCHED_AS_NULL)) {
                foreach ($hostVar as $key => $value) {
                    $route['arguments'][$key] = $matches[$key] ?? $route['defaults'][$key] ?? $value;
                }
            }
        } elseif (isset($route['schemes']) && !isset($route['schemes'][$uri->getScheme()])) {
            $errors[1] += $route['schemes'] ?? [];
            $matched = false;
        }

        return $matched;
    }

    /**
     * @return null|array<string,mixed>
     */
    protected function resolveRoute(string $path, string $method, UriInterface $uri): ?array
    {
        $errors = [[], []];

        foreach ($this->getCollection()->getRoutes() as $i => $r) {
            if (isset($r['prefix']) && !\str_starts_with($path, $r['prefix'])) {
                continue;
            }
            [$p, $v] = $this->optimized[$i] ??= $this->compiler->compile($r['path'], $r['placeholders'] ?? []);

            if (!\preg_match($p, $path, $m, \PREG_UNMATCHED_AS_NULL) || !$this->assertRoute($method, $uri, $r, $errors)) {
                continue;
            }

            foreach ($v as $key => $value) {
                $r['arguments'][$key] = $m[$key] ?? $r['defaults'][$key] ?? $value;
            }

            return $r;
        }

        return $this->resolveError($errors, $method, $uri);
    }

    /**
     * @return null|array<string,mixed>
     */
    protected function resolveCache(string $path, string $method, UriInterface $uri): ?array
    {
        $errors = [[], []];
        $routes = $this->optimized[2] ?? $this->doCache();

        foreach ($this->optimized[0][$path] ?? $this->optimized[1][0] ?? [] as $s => $h) {
            if (\is_int($s)) {
                $r = $routes[$h] ?? $routes->getRoutes()[$h];

                if (!$this->assertRoute($method, $uri, $r, $errors)) {
                    continue;
                }

                return $r;
            }

            if (!\str_starts_with($path, $s)) {
                continue;
            }

            foreach ($h as $p) {
                if (\preg_match($p, $path, $m, \PREG_UNMATCHED_AS_NULL)) {
                    $r = $routes[$o = (int) $m['MARK']] ?? $routes->getRoutes()[$o];

                    if ($this->assertRoute($method, $uri, $r, $errors)) {
                        $i = 0;

                        foreach ($this->optimized[1][1][$o] ?? [] as $key => $value) {
                            $r['arguments'][$key] = $m[++$i] ?? $r['defaults'][$key] ?? $value;
                        }

                        return $r;
                    }
                }
            }
        }

        return $this->resolveError($errors, $method, $uri);
    }

    /**
     * @param array<int,array<string,bool|string>> $errors
     */
    protected function resolveError(array $errors, string $method, UriInterface $uri)
    {
        if (!empty($errors[0])) {
            throw new MethodNotAllowedException(\array_keys($errors[0]), $uri->getPath(), $method);
        }

        if (!empty($errors[1])) {
            throw new UriHandlerException(
                \sprintf(
                    'Route with "%s" path requires request scheme(s) [%s], "%s" is invalid.',
                    $uri->getPath(),
                    \implode(', ', \array_keys($errors[1])),
                    $uri->getScheme(),
                ),
                400
            );
        }

        return null;
    }
}
