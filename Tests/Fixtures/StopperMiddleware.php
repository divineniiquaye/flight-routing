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

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class StopperMiddleware.
 */
class StopperMiddleware implements MiddlewareInterface
{
    /**
     * @var string
     */
    public $content;

    /**
     * @var array
     */
    public static $output = [];

    private $response;

    /**
     * SampleMiddleware constructor.
     *
     * @param string $content
     */
    public function __construct(ResponseFactoryInterface $responseHandler, string $content = null)
    {
        $this->content  = $content ?: \mt_rand(1, 9999999);
        $this->response = $responseHandler->createResponse();
    }

    /**
     * {@inheritdoc}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        static::$output[] = $this->content;

        $this->response->getBody()->write('Stopped in middleware.');
        $response = $this->response->withHeader('Content-Type', 'text/plain; charset=utf-8');

        return $response;
    }
}
