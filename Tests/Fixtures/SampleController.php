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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class SampleController.
 */
class SampleController
{
    /**
     * @return string
     */
    public function homePageString(): string
    {
        return 'Welcome to Flight Router HomePage';
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return string
     */
    public function homePageRequestString(ServerRequestInterface $request): string
    {
        return 'Hello, I\'m on a ' . $request->getMethod() . ' method';
    }

    /**
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     */
    public function homePageResponse(ResponseInterface $response): ResponseInterface
    {
        $response->getBody()->write('Welcome to Flight Router HomePage');

        return $response;
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     *
     * @return ResponseInterface
     */
    public function homePageRequestResponse(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $response->getBody()->write('Hello, I\'m on a ' . $request->getMethod() . ' method');

        return $response;
    }

    public function homePageRequestArray($arrayResponse = null)
    {
        return null === $arrayResponse ? ['Hello Array', 'Cool Man'] : $arrayResponse;
    }
}
