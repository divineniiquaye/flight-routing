<?php

declare(strict_types=1);

/*
 * This file is part of Flight Routing.
 *
 * PHP version 8.0 and above required
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 Divine Niiquaye Ibok (https://divinenii.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flight\Routing\Tests\Fixtures\Annotation\Route\Invalid;

use Flight\Routing\Annotation\Route;

class MethodWithResource
{
    /**
     * @Route(
     *   name="method_with_resource",
     *   path="/method_with_resource",
     *   methods={"HEAD", "POST"},
     *   resource="action"
     * )
     */
    public function isInvalid(): void
    {
    }
}
