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

use BiuradPHP\Http\Factory\UriFactory;
use DivineNii\Invoker\Interfaces\InvokerInterface;
use Flight\Routing\Interfaces\RouteCollectionInterface;
use Flight\Routing\Interfaces\RouteCollectorInterface;
use Flight\Routing\Interfaces\RouteFactoryInterface;
use Flight\Routing\Interfaces\RouteMatcherInterface;
use Flight\Routing\Route;
use Flight\Routing\RouteCollection;
use Flight\Routing\RouteCollector;
use Flight\Routing\Router;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
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
        return new UriFactory();
    }

    /**
     * @param string                               $method  — HTTP method
     * @param string|UriInterface                  $uri     — URI
     * @param array<string,string>                 $headers — Request headers
     * @param null|resource|StreamInterface|string $body    — Request body
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
     * @param Route                    $route
     * @param RouteCollectionInterface $collection
     *
     * @return RouteFactoryInterface
     */
    public function getRouteFactory(Route $route = null, RouteCollectionInterface $collection = null)
    {
        $expectedRoute      = $route ?? new Fixtures\TestRoute();
        $expectedCollection = $collection ?? new RouteCollection();

        $routeFactory = $this->createMock(RouteFactoryInterface::class);
        $routeFactory->method('createRoute')->willReturn($expectedRoute);
        $routeFactory->method('createCollection')->willReturn($expectedCollection);

        return $routeFactory;
    }

    /**
     * @param null|RouteMatcherInterface $matcher
     * @param null|InvokerInterface      $resolver
     * @param null|ContainerInterface    $container
     *
     * @return Router
     */
    public function getRouter(
        ?RouteMatcherInterface $matcher = null,
        ?InvokerInterface $resolver = null,
        bool $profiler = false
    ): Router {
        return new Router(
            $this->getResponseFactory(),
            $this->getUriFactory(),
            $matcher,
            $resolver,
            $profiler
        );
    }

    /**
     * @param RouteCollectionInterface $collection
     *
     * @return RouteCollectorInterface
     */
    public function getRouteCollector(Route $route = null, RouteCollectionInterface $collection = null)
    {
        return new RouteCollector($this->getRouteFactory($route, $collection));
    }
}
