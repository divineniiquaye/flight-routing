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
use Flight\Routing\Interfaces\RouteCollectionInterface;
use Flight\Routing\Interfaces\RouteCollectorInterface;
use Flight\Routing\Interfaces\RouteFactoryInterface;
use Flight\Routing\Route;
use Flight\Routing\RouteCollection;
use Flight\Routing\RouteCollector;
use Flight\Routing\Router;
use Flight\Routing\Services\SimpleRouteMatcher;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Uri;
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
     * @param string $uri
     *
     * @return UriFactoryInterface
     */
    public function getUriFactory(string $uri = '')
    {
        $uriFactory = $this->createMock(UriFactoryInterface::class);
        $uriFactory->method('createUri')->willReturn(new Uri($uri));

        return $uriFactory;
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
        $routeFactory->method('createMatcher')->willReturn(new SimpleRouteMatcher());

        return $routeFactory;
    }

    /**
     * @param string                     $uri
     * @param null|RouteFactoryInterface $factory
     * @param null|InvokerInterface      $resolver
     * @param null|ContainerInterface    $container
     *
     * @return Router
     */
    public function getRouter(
        string $uri = '',
        ?RouteFactoryInterface $factory = null,
        ?InvokerInterface $resolver = null,
        bool $profiler = false
    ): Router {
        return new Router(
            $this->getResponseFactory(),
            $this->getUriFactory($uri),
            $factory,
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
