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

trait DefaultsTrait
{
    /**
     * Route defaults.
     *
     * @var array
     */
    protected $defaults = [];

    /**
     * {@inheritdoc}
     */
    public function addDefaults(array $defaults): RouteInterface
    {
        $this->defaults = \array_merge($defaults, $this->defaults);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefault(string $name, ?string $default = null): ?string
    {
        if ($this->hasDefault($name)) {
            return $this->defaults[$name];
        }

        return $default;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaults(): array
    {
        return $this->defaults;
    }

    /**
     * {@inheritdoc}
     */
    public function hasDefault(string $name): bool
    {
        return \array_key_exists($name, $this->defaults);
    }
}
