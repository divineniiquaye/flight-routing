<?php declare(strict_types=1);

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

namespace Flight\Routing\Tests\Fixtures\Annotation\Route\Valid;

use Flight\Routing\Annotation\Route;
use JsonSerializable;

/**
 * @Route("testing/*<handleSomething>", methods={"GET", "HEAD"})
 */
class MethodOnRoutePattern implements JsonSerializable
{
    public function handleSomething(): JsonSerializable
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return ['Hello' => 'World to Flight Routing'];
    }
}
