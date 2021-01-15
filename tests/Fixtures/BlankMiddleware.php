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

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * BlankMiddleware
 */
class BlankMiddleware implements MiddlewareInterface
{
    /**
     * @var bool
     */
    private $isBroken;

    /**
     * @var bool
     */
    private $isRunned = false;

    /**
     * @param bool $isBroken
     */
    public function __construct(bool $isBroken = false)
    {
        $this->isBroken = $isBroken;
    }

    /**
     * @return string
     */
    public function getHash(): string
    {
        return \spl_object_hash($this);
    }

    /**
     * @return bool
     */
    public function isBroken(): bool
    {
        return $this->isBroken;
    }

    /**
     * @return bool
     */
    public function isRunned(): bool
    {
        return $this->isRunned;
    }

    /**
     * {@inheritDoc}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->isRunned = true;

        if ($this->isBroken) {
            return (new Psr17Factory())->createResponse();
        }

        $response = $handler->handle($request);

        if (\array_key_exists('Broken', $request->getServerParams())) {
            $response = $response->withHeader('Middleware-Broken', 'broken');
        }

        return $response->withHeader('Middleware', 'test');
    }
}
