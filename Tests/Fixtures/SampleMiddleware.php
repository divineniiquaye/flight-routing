<?php

declare(strict_types=1);

/*
 * This file is part of Flight Routing.
 *
 * PHP version 7.2 and above required
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 Biurad Group (https://biurad.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flight\Routing\Tests\Fixtures;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class SampleMiddleware.
 */
class SampleMiddleware implements MiddlewareInterface
{
    /**
     * @var string
     */
    public $content;

    /**
     * @var array
     */
    public static $output = [];

    /**
     * SampleMiddleware constructor.
     *
     * @param null|string $content
     */
    public function __construct(string $content = null)
    {
        static::$output = [];

        $this->content = $content ?: \mt_rand(1, 9999999);
    }

    /**
     * {@inheritdoc}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        static::$output[] = $this->content;
        $request          = $request->withAttribute(__CLASS__, $this->content);

        return $handler->handle($request);
    }
}
