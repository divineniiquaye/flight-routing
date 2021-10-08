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

/**
 * @internal
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
final class GeneratedRoute
{
    /** @var array<string,int> */
    private array $staticPaths;

    private ?string $dynamicRegex;

    /** @var array<int,mixed[]> */
    private array $variables;

    /**
     * @param array<string,int> $staticPaths
     * @param array<int,mixed[]> $variables
     */
    public function __construct(array $staticPaths, ?string $dynamicRegex, array $variables)
    {
        $this->dynamicRegex = $dynamicRegex;
        $this->staticPaths = $staticPaths;
        $this->variables = $variables;
    }

    /**
     * @internal
     */
    public function __serialize(): array
    {
        return [$this->staticPaths, $this->dynamicRegex, $this->variables];
    }

    /**
     * @internal
     *
     * @param array<int,mixed> $data
     */
    public function __unserialize(array $data): void
    {
        [$this->staticPaths, $this->dynamicRegex, $this->variables] = $data;
    }

    public function getData(): array
    {
        return [$this->staticPaths, $this->dynamicRegex, $this->variables];
    }
}
