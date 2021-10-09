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

use Flight\Routing\RouteMatcher;
use Psr\Http\Message\UriInterface;

/**
 * @internal
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
final class GeneratedRoute
{
    /** @var array<int,mixed> */
    private array $compiledData;

    /**
     * @param array<string,int> $staticPaths
     * @param array<int,mixed[]> $variables
     */
    public function __construct(array $staticPaths, ?string $dynamicRegex, array $variables)
    {
        $this->compiledData = [$staticPaths, $dynamicRegex, $variables];
    }

    public function getData(): array
    {
        return $this->compiledData;
    }

    /**
     * @return array<int,mixed>|null
     */
    public function matchRoute(string $requestPath, UriInterface $uri): ?array
    {
        [$staticRoutes, $regexList, $variables] = $this->compiledData;

        if (null === $matchedId = $staticRoutes[$requestPath] ?? null) {
            if (null === $regexList || !\preg_match($regexList, $requestPath, $matches, \PREG_UNMATCHED_AS_NULL)) {
                return null;
            }

            $matchedId = (int) $matches['MARK'];
        }

        foreach ($variables as $domain => $routeVar) {
            if (!\is_null($matchVar = $routeVar[$matchedId] ?? null)) {
                if (!empty($matchVar)) {
                    $matchInt = 0;
                    $hostsVar = 0 === $domain ? [] : RouteMatcher::matchHost($domain, $uri);

                    foreach ($matchVar as $key => $value) {
                        $matchVar[$key] = $matches[++$matchInt] ?? $matches[$key] ?? $hostsVar[$key] ?? $value;
                    }
                }

                return [$matchedId, $matchVar];
            }
        }

        return null;
    }
}
