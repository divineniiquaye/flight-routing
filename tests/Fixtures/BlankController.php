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

/**
 * BlankController
 */
class BlankController
{
    /**
     * @var bool
     */
    private $isRunned = false;

    /**
     * @var array
     */
    private $attributes = [];

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     *
     * @link https://www.php.net/manual/en/language.oop5.magic.php#object.invoke
     */
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        return $this->handle($request);
    }

    /**
     * @return bool
     */
    public function isRunned(): bool
    {
        return $this->isRunned;
    }

    /**
     * @return array<string,mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @param mixed $key
     *
     * @return mixed
     */
    public function getAttribute($key)
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->isRunned   = true;
        $this->attributes = $request->getAttributes();

        return (new Psr17Factory())->createResponse();
    }

    public static function process(ServerRequestInterface $request): ResponseInterface
    {
        return (new Psr17Factory())->createResponse();
    }
}
