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

use Flight\Routing\Interfaces\RouteInterface;

trait PatternsTrait
{
    /**
     * Route Patterns
     *
     * @var array
     */
    protected $patterns = [];

    /**
     * {@inheritdoc}
     */
    public function addPattern(string $name, string $expression = null): RouteInterface
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
        $this->patterns = array_merge($wheres, $this->patterns);

        return $this;
    }
}
