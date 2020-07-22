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

use Flight\Routing\Interfaces\RouteInterface;

trait PatternsTrait
{
    /**
     * Route Patterns.
     *
     * @var array<string,string>
     */
    protected $patterns = [];

    /**
     * {@inheritdoc}
     */
    public function addPattern(string $name, string $expression): RouteInterface
    {
        $this->patterns[$name] = $expression;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getPatterns(): array
    {
        return $this->patterns;
    }

    /**
     * {@inheritdoc}
     */
    public function whereArray(array $wheres = []): RouteInterface
    {
        $this->patterns = \array_merge($wheres, $this->patterns);

        return $this;
    }
}
