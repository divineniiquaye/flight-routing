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

use Flight\Routing\Route as BaseRoute;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * TestRoute
 */
class TestRoute extends BaseRoute
{
    /**
     * @var int
     */
    public const WITH_BROKEN_MIDDLEWARE = 1;

    /**
     * Constructor of the class
     *
     * @param int $flags
     */
    public function __construct(int $flags = 0)
    {
        parent::__construct(
            self::getTestRouteName($flags),
            self::getTestRouteMethods($flags),
            self::getTestRoutePath($flags),
            self::getTestRouteRequestHandler($flags)
        );

        $this->addMiddleware(...self::getTestRouteMiddlewares($flags));
        $this->setDefaults(self::getTestRouteAttributes($flags));
    }

    /**
     * @return string
     */
    public static function getTestRouteName(int $flags = 0): string
    {
        return \uniqid() . '.' . \uniqid() . '.' . \uniqid();
    }

    /**
     * @return string
     */
    public static function getTestRoutePath(int $flags = 0): string
    {
        return '/' . \uniqid() . '/' . \uniqid() . '/' . \uniqid();
    }

    /**
     * @return string[]
     */
    public static function getTestRouteMethods(int $flags = 0): array
    {
        return [
            \strtoupper(\uniqid()),
            \strtoupper(\uniqid()),
            \strtoupper(\uniqid()),
        ];
    }

    /**
     * @return RequestHandlerInterface
     */
    public static function getTestRouteRequestHandler(int $flags = 0): RequestHandlerInterface
    {
        return new BlankRequestHandler();
    }

    /**
     * @return MiddlewareInterface[]
     */
    public static function getTestRouteMiddlewares(int $flags = 0): array
    {
        $middlewares = [new BlankMiddleware()];

        if ($flags & self::WITH_BROKEN_MIDDLEWARE) {
            $middlewares[] = new BlankMiddleware(true);
        } else {
            $middlewares[] = new BlankMiddleware();
        }

        $middlewares[] = new BlankMiddleware();

        return $middlewares;
    }

    /**
     * @return array<string,string>
     */
    public static function getTestRouteAttributes(int $flags = 0): array
    {
        return [
            \uniqid() => \uniqid(),
            \uniqid() => \uniqid(),
            \uniqid() => \uniqid(),
        ];
    }
}
