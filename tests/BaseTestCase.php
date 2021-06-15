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

namespace Flight\Routing\Tests;

use DivineNii\Invoker\Interfaces\InvokerInterface;
use Flight\Routing\Router;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

class BaseTestCase extends TestCase
{
    /**
     * @param ResponseInterface $response
     *
     * @return ResponseFactoryInterface
     */
    public function getResponseFactory(ResponseInterface $response = null)
    {
        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->method('CreateResponse')
            ->willReturn($response ?? new Response());

        return $responseFactory;
    }

    /**
     * @return UriFactoryInterface
     */
    public function getUriFactory()
    {
        return new Psr17Factory();
    }

    /**
     * @param string                               $method  — HTTP method
     * @param string|UriInterface                  $uri     — URI
     * @param array<string,string>                 $headers — Request headers
     * @param resource|StreamInterface|string|null $body    — Request body
     *
     * @return ServerRequestFactoryInterface
     */
    public function getServerRequestFactory(string $method, $uri, $headers = [], $body = null)
    {
        $serverReequestFactory = $this->createMock(ServerRequestFactoryInterface::class);
        $serverReequestFactory->method('createServerRequest')
            ->willReturn(new ServerRequest($method, $uri, $headers, $body));

        return $serverReequestFactory;
    }

    /**
     * @param null|class-string       $matcher
     * @param ContainerInterface|null $container
     */
    public function getRouter(?InvokerInterface $resolver = null, bool $profiler = false): Router
    {
        $router = new Router($this->getResponseFactory(), null, null, $profiler);

        if (null !== $resolver) {
            $router->setHandlerResolver([$resolver, 'call']);
        }

        return $router;
    }
}
