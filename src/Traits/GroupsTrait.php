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

use Flight\Routing\Interfaces\RouteGroupInterface;

trait GroupsTrait
{
    /**
     * @var bool
     */
    protected $groupAppended = false;

    /**
     * Parent route groups
     *
     * @var RouteGroupInterface[]|array
     */
    protected $groups = [];

    /**
     * {@inheritdoc}
     */
    public function hasGroup(): bool
    {
        return $this->groupAppended;
    }

    /**
     * {@inheritdoc}
     */
    public function getGroupId(): ?string
    {
        if (!$this->hasGroup()) {
            return null;
        }

        return md5(serialize($this->groups));
    }

    /**
     * @param RouteGroupInterface[]|array $groups
     * @return void
     */
    protected function appendGroupToRoute(array $groups): void
    {
        if (empty($groups)) {
            return;
        }

        // If Groups are more, move to the next group, else stick to current group.
        $this->groups = count($groups) > 1 ? next($groups)->getOptions() : current($groups)->getOptions();

        if (isset($this->groups[RouteGroupInterface::MIDDLEWARES])) {
            $this->middlewares = array_merge($this->middlewares, $this->groups[RouteGroupInterface::MIDDLEWARES]);
        }

        if (isset($this->groups[RouteGroupInterface::DOMAIN])) {
            $this->domain = $this->groups[RouteGroupInterface::DOMAIN] ?? '';
        }

        if (isset($this->groups[RouteGroupInterface::NAME])) {
            $this->name = $this->groups[RouteGroupInterface::NAME] ?? null;
        }

        if (isset($this->groups[RouteGroupInterface::PREFIX])) {
            $this->prefix = $this->groups[RouteGroupInterface::PREFIX] ?? null;
        }

        $this->groupAppended = true;
    }
}
