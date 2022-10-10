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

namespace Flight\Routing\Tests\Fixtures;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * BlankRequestHandler.
 */
class BlankRequestHandler implements RequestHandlerInterface
{
    private bool $isDone = false;

    /**
     * @param array<string,mixed> $attributes
     */
    public function __construct(private array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    public static function __set_state(array $properties): static
    {
        $new = new static();

        foreach ($properties as $property => $value) {
            $new->{$property} = $value;
        }

        return $new;
    }

    public function isDone(): bool
    {
        return $this->isDone;
    }

    /**
     * @return array<string,mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     */
    public function getAttribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->attributes = $request->getAttributes();

        try {
            return (new Psr17Factory())->createResponse();
        } finally {
            $this->isDone = true;
        }
    }
}
