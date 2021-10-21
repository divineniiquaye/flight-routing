<?php

declare(strict_types=1);

/*
 * This file is part of Flight Routing.
 *
 * PHP version 7.4 and above required
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 Biurad Group (https://biurad.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flight\Routing\Generator;

use Flight\Routing\Interfaces\RouteGeneratorInterface;
use Psr\Http\Message\UriInterface;

/**
 * The default compiled routes match for route compiler class.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
final class GeneratedRoute implements RouteGeneratorInterface
{
    /** @var array<int,mixed> */
    private array $compiledData;

    /**
     * @param array<string,array<int|string,array<int,int>|int>> $staticPaths
     * @param array<string,array<int,mixed[]>> $variables
     */
    public function __construct(array $staticPaths, ?string $dynamicRegex, array $variables)
    {
        $this->compiledData = [$staticPaths, $dynamicRegex, $variables];
    }

    /**
     * @internal
     */
    public function __serialize(): array
    {
        return $this->compiledData;
    }

    /**
     * @internal
     *
     * @param array<int,mixed> $data
     */
    public function __unserialize(array $data): void
    {
        $this->compiledData = $data;
    }

    /**
     * {@inheritdoc}
     */
    public function getData(): array
    {
        return $this->compiledData;
    }

    /**
     * {@inheritdoc}
     */
    public function match(string $method, UriInterface $uri, callable $routes)
    {
        [$staticRoutes, $regexList, $variables] = $this->compiledData;
        $requestPath = $uri->getPath();
        $matches = [];

        if (empty($matchedIds = $staticRoutes[$requestPath] ?? [])) {
            if (null === $regexList || !\preg_match($regexList, $requestPath, $matches, \PREG_UNMATCHED_AS_NULL)) {
                if (isset($staticRoutes['*'][$requestPath])) {
                    $matchedIds = $staticRoutes['*'][$requestPath];
                    goto found_a_route_match;
                }

                return null;
            }

            $matchedIds = [(int) $matches['MARK']];
        }

        found_a_route_match:
        foreach ($matchedIds as $matchedId) {
            foreach ($variables[$method][$matchedId] ?? [] as $domain => $routeVar) {
                [$matchedRoute, $hostsVar] = $routes($matchedId, $domain ?: null, $uri);
                $requiredSchemes = $matchedRoute->getSchemes();

                if (null === $hostsVar) {
                    continue;
                }

                if ($requiredSchemes && !\in_array($uri->getScheme(), $requiredSchemes)) {
                    continue;
                }

                if (!empty($routeVar)) {
                    $matchInt = 0;

                    foreach ($routeVar as $key => $value) {
                        $matchedRoute->argument($key, $matches[++$matchInt] ?? $matches[$key] ?? $hostsVar[$key] ?? $value);
                    }
                }

                return $matchedRoute;
            }
        }

        return $matchedIds;
    }
}
