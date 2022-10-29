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

namespace Flight\Routing\Tests\Fixtures\Annotation\Route\Valid\Subdir;

use Flight\Routing\Annotation\Route;
use Flight\Routing\Tests\Fixtures\BlankRequestHandler;

/**
 * @Route(
 *   name="sub-dir:bar",
 *   path="/sub-dir/bar",
 *   methods={"HEAD", "GET"},
 *   defaults={
 *     "foo": "bar",
 *     "bar": "baz"
 *   }
 * )
 */
class BarRequestHandler extends BlankRequestHandler
{
}
