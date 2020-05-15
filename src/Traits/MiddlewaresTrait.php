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

trait MiddlewaresTrait
{
    /**
     * Route Middlewares
     *
     * @var array
     */
    protected $middlewares = [];

    /**
     * {@inheritdoc}
     */
    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    /**
     * {@inheritdoc}
     */
    public function addMiddleware($middleware): RouteInterface
    {
        $this->middlewares = array_merge((array) $middleware, $this->middlewares);

        return $this;
    }
}
