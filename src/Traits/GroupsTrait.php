<?php

declare(strict_types=1);

/*
 * This file is part of Flight Routing.
 *
 * PHP version 7.2 and above required
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 Biurad Group (https://biurad.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
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
     * Parent route groups.
     *
     * @var array|RouteGroupInterface[]
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

        return \md5(\serialize($this->groups));
    }

    /**
     * @param RouteGroupInterface $group
     */
    protected function appendGroupToRoute(?RouteGroupInterface $group): void
    {
        if (null === $group) {
            return;
        }

        // If Groups, stick to current group.
        $this->groups = $group->getOptions();

        if (isset($this->groups[RouteGroupInterface::MIDDLEWARES])) {
            $this->middlewares = \array_merge($this->middlewares, $this->groups[RouteGroupInterface::MIDDLEWARES]);
        }

        $this->domain  = $this->groups[RouteGroupInterface::DOMAIN] ?? '';
        $this->name    = $this->groups[RouteGroupInterface::NAME] ?? null;
        $this->prefix  = $this->groups[RouteGroupInterface::PREFIX] ?? null;
        $this->schemes = $this->groups[RouteGroupInterface::SCHEMES] ?? null;

        $this->groupAppended = true;
    }
}
