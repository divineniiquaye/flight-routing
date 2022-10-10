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

namespace Flight\Routing\Tests\Benchmarks;

use Flight\Routing\Exceptions\MethodNotAllowedException;
use Flight\Routing\Interfaces\RouteMatcherInterface;
use Flight\Routing\{RouteCollection, Router};

/**
 * @Groups({"real"})
 */
final class RealExampleBench extends RouteBench
{
    /**
     * {@inheritdoc}
     */
    public function createDispatcher(string $cache = null): RouteMatcherInterface
    {
        $router = new Router(null, $cache);
        $router->setCollection(static function (RouteCollection $routes): void {
            $routes->add('/', ['GET'])->bind('home');
            $routes->add('/page/{page_slug:[a-zA-Z0-9\-]+}', ['GET'])->bind('page.show');
            $routes->add('/about-us', ['GET'])->bind('about-us');
            $routes->add('/contact-us', ['GET'])->bind('contact-us');
            $routes->add('/contact-us', ['POST'])->bind('contact-us.submit');
            $routes->add('/blog', ['GET'])->bind('blog.index');
            $routes->add('/blog/recent', ['GET'])->bind('blog.recent');
            $routes->add('/blog/post/{post_slug:[a-zA-Z0-9\-]+}', ['GET'])->bind('blog.post.show');
            $routes->add('/blog/post/{post_slug:[a-zA-Z0-9\-]+}/comment', ['POST'])->bind('blog.post.comment');
            $routes->add('/shop', ['GET'])->bind('shop.index');
            $routes->add('/shop/category', ['GET'])->bind('shop.category.index');
            $routes->add('/shop/category/search/{filter_by:[a-zA-Z]+}:{filter_value}', ['GET'])->bind('shop.category.search');
            $routes->add('/shop/category/{category_id:\d+}', ['GET'])->bind('shop.category.show');
            $routes->add('/shop/category/{category_id:\d+}/product', ['GET'])->bind('shop.category.product.index');
            $routes->add('/shop/category/{category_id:\d+}/product/search/{filter_by:[a-zA-Z]+}:{filter_value}', ['GET'])->bind('shop.category.product.search');
            $routes->add('/shop/product', ['GET'])->bind('shop.product.index');
            $routes->any('/shop[/{type:\w+}[:{filter_by:\@.*}]')->bind('shop.api')->domain('api.shop.com');
            $routes->add('/shop/product/search/{filter_by:[a-zA-Z]+}:{filter_value}', ['GET'])->bind('shop.product.search');
            $routes->add('/shop/product/{product_id:\d+}', ['GET'])->bind('shop.product.show');
            $routes->add('/shop/cart', ['GET'])->bind('shop.cart.show');
            $routes->add('/shop/cart', ['PUT', 'DELETE'])->bind('shop.cart.add_remove');
            $routes->add('/shop/cart/checkout', ['GET', 'POST'])->bind('shop.cart.checkout');
            $routes->add('/admin/login', ['GET', 'POST'])->bind('admin.login');
            $routes->add('/admin/logout', ['GET'])->bind('admin.logout');
            $routes->add('/admin', ['GET'])->bind('admin.index');
            $routes->add('/admin/product', ['GET'])->bind('admin.product.index');
            $routes->add('/admin/product/create', ['GET'])->bind('admin.product.create');
            $routes->add('/admin/product', ['POST'])->bind('admin.product.store');
            $routes->add('/admin/product/{product_id:\d+}', ['GET', 'PUT', 'PATCH', 'DELETE'])->bind('admin.product');
            $routes->add('/admin/product/{product_id:\d+}/edit', ['GET'])->bind('admin.product.edit');
            $routes->add('/admin/category', ['GET', 'POST'])->bind('admin.category.index_store');
            $routes->add('/admin/category/create', ['GET'])->bind('admin.category.create');
            $routes->add('/admin/category/{category_id:\d+}', ['GET', 'PUT', 'PATCH', 'DELETE'])->bind('admin.category');
            $routes->add('/admin/category/{category_id:\d+}/edit', ['GET'])->bind('admin.category.edit');
            $routes->sort(); // Sort routes by giving more priority to static like routes
        });

        return $router;
    }

    /**
     * {@inheritdoc}
     */
    public function provideStaticRoutes(): iterable
    {
        yield 'first' => [
            'method' => 'GET',
            'route' => '/',
            'result' => ['handler' => null, 'prefix' => '/', 'path' => '/', 'methods' => ['GET' => true], 'name' => 'home'],
        ];

        yield 'middle' => [
            'method' => 'GET',
            'route' => '/shop/product',
            'result' => ['handler' => null, 'prefix' => '/shop/product', 'path' => '/shop/product', 'methods' => ['GET' => true], 'name' => 'shop.product.index'],
        ];

        yield 'last' => [
            'method' => 'GET',
            'route' => '/admin/category',
            'result' => ['handler' => null, 'prefix' => '/admin/category', 'path' => '/admin/category', 'methods' => ['GET' => true, 'POST' => true], 'name' => 'admin.category.index_store'],
        ];

        yield 'invalid-method' => [
            'method' => 'PUT',
            'route' => '/about-us',
            'result' => MethodNotAllowedException::class,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function provideDynamicRoutes(): iterable
    {
        yield 'first' => [
            'method' => 'GET',
            'route' => '/page/hello-word',
            'result' => [
                'handler' => null,
                'prefix' => '/page',
                'path' => '/page/{page_slug:[a-zA-Z0-9\-]+}',
                'methods' => ['GET' => true],
                'name' => 'page.show',
                'arguments' => ['page_slug' => 'hello-word'],
            ],
        ];

        yield 'middle' => [
            'method' => 'GET',
            'route' => '//api.shop.com/shop/category_search:filter_by@furniture?value=chair',
            'result' => [
                'handler' => null,
                'hosts' => ['api.shop.com' => true],
                'prefix' => '/shop',
                'path' => '/shop[/{type:\w+}[:{filter_by:\@.*}]',
                'methods' => \array_fill_keys(Router::HTTP_METHODS_STANDARD, true),
                'name' => 'shop.api',
                'arguments' => ['type' => 'category_search', 'filter_by' => 'furniture'],
            ],
        ];

        yield 'last' => [
            'method' => 'GET',
            'route' => '/admin/category/123/edit',
            'result' => [
                'handler' => null,
                'prefix' => '/admin/category',
                'path' => '/admin/category/{category_id:\d+}/edit',
                'methods' => ['GET' => true],
                'name' => 'admin.category.edit',
                'arguments' => ['category_id' => '123'],
            ],
        ];

        yield 'invalid-method' => [
            'method' => 'PATCH',
            'route' => '/shop/category/123',
            'result' => MethodNotAllowedException::class,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function provideOtherScenarios(): iterable
    {
        yield 'non-existent' => [
            'method' => 'GET',
            'route' => '/shop/product/awesome',
            'result' => null,
        ];

        yield 'longest-route' => [
            'method' => 'GET',
            'route' => '/shop/category/123/product/search/status:sale',
            'result' => [
                'handler' => null,
                'prefix' => '/admin/category/123/edit',
                'path' => '/shop/category/{category_id:\d+}/product/search/{filter_by:[a-zA-Z]+}:{filter_value}',
                'methods' => ['GET' => true],
                'name' => 'shop.category.product.search',
                'arguments' => ['category_id' => '123', 'filter_by' => 'status', 'filter_value' => 'sale'],
            ],
        ];
    }
}
