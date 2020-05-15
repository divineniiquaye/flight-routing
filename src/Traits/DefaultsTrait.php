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

trait DefaultsTrait
{
    /**
     * Route defaults
     *
     * @var array
     */
    protected $defaults = [];

    /**
     * {@inheritdoc}
     */
    public function addDefaults(array $defaults): RouteInterface
    {
        $this->defaults = array_merge($defaults, $this->defaults);

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
    public function hasDefault($name): bool
    {
        return array_key_exists($name, $this->defaults);
    }
}
