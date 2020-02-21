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

namespace Flight\Routing\Interfaces;

use BiuradPHP\Http\Interfaces\EmitterInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;

/**
 * Interface PublisherInterface
 * Publishers are responsible to publish the response provided by controllers
 */
interface PublisherInterface
{
    /**
     * Publish the content
     *
     * @param PsrResponseInterface|string|mixed $content
     * @param EmitterInterface|null $response
     *
     * @return bool
     */
    public function publish($content, ?EmitterInterface $response);
}
