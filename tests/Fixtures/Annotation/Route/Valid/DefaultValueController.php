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

namespace Flight\Routing\Tests\Fixtures\Annotation\Route\Valid;

use Flight\Routing\Annotation\Route;

class DefaultValueController
{
    /**
     * @Route("/{default}/path", methods={"GET", "POST"}, name="action")
     */
    public function action($default = 'value'): void
    {
    }

    /**
     * @Route(
     *     "/hello/{name:\w+}",
     *     methods={"GET", "POST"},
     *     name="hello_without_default"
     * )
     * @Route(
     *     "/cool/{name=<Symfony>}",
     *     where={"name": "\w+"},
     *     methods={"GET", "POST"},
     *     name="hello_with_default"
     * )
     */
    public function hello(string $name = 'World'): void
    {
    }

    private function notAccessed(): void
    {
    }
}
