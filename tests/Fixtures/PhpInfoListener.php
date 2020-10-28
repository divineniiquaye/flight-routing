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

namespace Flight\Routing\Tests\Fixtures;

use Flight\Routing\Interfaces\RouteInterface;
use Flight\Routing\Interfaces\RouteListenerInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * PhpInfoListener
 */
class PhpInfoListener implements RouteListenerInterface
{
    /**
     * {@inheritDoc}
     */
    public function onRoute(ServerRequestInterface $request, RouteInterface &$route, callable $callable): void
    {
        if (is_string($callable) && 'phpinfo' === $callable) {
            $route->setArguments(['what' => -1]);
        }
    }
}
