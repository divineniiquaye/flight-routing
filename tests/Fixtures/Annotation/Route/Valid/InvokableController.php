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

/**
 * @Route("/here", "lol", methods={"GET", "POST"}, schemes={"https"})
 */
class InvokableController
{
    public function __invoke(): void
    {
    }
}
