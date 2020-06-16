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

namespace Flight\Routing\Interfaces;

interface RouteGroupInterface
{
    public const MIDDLEWARES = 'middlewares';

    public const PREFIX = 'prefix';

    public const NAMESPACE = 'namespace';

    public const DOMAIN = 'domain';

    public const NAME = 'name';

    public const DEFAULTS = 'defaults';

    public const REQUIREMENTS = 'patterns';

    public const SCHEMES = 'schemes';

    /**
     * @return RouteGroupInterface
     */
    public function collectRoutes(): self;

    /**
     * Get The Route Group Options.
     *
     * @return array
     */
    public function getOptions(): array;
}
