<div align="center">

# The PHP HTTP Flight Routing

[![PHP Version](https://img.shields.io/packagist/php-v/divineniiquaye/flight-routing.svg?style=flat-square&colorB=%238892BF)](http://php.net)
[![Latest Version](https://img.shields.io/packagist/v/divineniiquaye/flight-routing.svg?style=flat-square)](https://packagist.org/packages/divineniiquaye/flight-routing)
[![Workflow Status](https://img.shields.io/github/workflow/status/divineniiquaye/flight-routing/build?style=flat-square)](https://github.com/divineniiquaye/flight-routing/actions?query=workflow%3Abuild)
[![Code Maintainability](https://img.shields.io/codeclimate/maintainability/divineniiquaye/flight-routing?style=flat-square)](https://codeclimate.com/github/divineniiquaye/flight-routing)
[![Coverage Status](https://img.shields.io/codecov/c/github/divineniiquaye/flight-routing?style=flat-square)](https://codecov.io/gh/divineniiquaye/flight-routing)
[![Psalm Type Coverage](https://img.shields.io/endpoint?style=flat-square&url=https%3A%2F%2Fshepherd.dev%2Fgithub%2Fdivineniiquaye%2Frade-di%2Fcoverage)](https://shepherd.dev/github/divineniiquaye/flight-routing)
[![Quality Score](https://img.shields.io/scrutinizer/g/divineniiquaye/flight-routing.svg?style=flat-square)](https://scrutinizer-ci.com/g/divineniiquaye/flight-routing)

</div>

---

Flight routing is yet another high performance HTTP router for [PHP][1]. It is simple, easy to use, scalable and fast. This library depends on [PSR-7][2] for route match and support using [PSR-15][3] for intercepting route before being rendered.

This library previous versions was inspired by [Sunrise Http Router][4], [Symfony Routing][5], [FastRoute][6] and now completely rewritten for better performance.

## 🏆 Features

- Supports all HTTP request methods (eg. `GET`, `POST` `DELETE`, etc).
- Regex Expression constraints for parameters.
- Reversing named routes paths to full URL with strict parameter checking.
- Route grouping and merging.
- Supports routes caching for performance.
- [PSR-15][3] Middleware (classes that intercepts before the route is rendered).
- Domain and sub-domain routing.
- Restful Routing.
- Supports PHP 8 attribute `#[Route]` and doctrine annotation `@Route` routing.
- Support custom matching strategy using custom route matcher class or compiler class.

## 📦 Installation

This project requires [PHP][1] 8.0 or higher. The recommended way to install, is via [Composer][7]. Simply run:

```bash
$ composer require divineniiquaye/flight-routing
```

I recommend reading [my blog post][8] on setting up Apache, Nginx, IIS server configuration for your [PHP][1] project.

## 📍 Quick Start

The default compiler accepts the following constraints in route pattern:

- `{name}` - required placeholder.
- `{name=foo}` - placeholder with default value.
- `{name:regex}` - placeholder with regex definition.
- `{name:regex=foo}` - placeholder with regex definition and default value.
- `[{name}]` - optional placeholder.

A name of a placeholder variable is simply an acceptable PHP function/method parameter name expected to be unique, while the regex definition and default value can be any string (i.e [^/]+).

- `/foo/` - Matches **/foo/** or **/foo**. ending trailing slashes are striped before matching.
- `/user/{id}` - Matches **/user/bob**, **/user/1234** or **/user/23/**.
- `/user/{id:[^/]+}` - Same as the previous example.
- `/user[/{id}]` - Same as the previous example, but also match **/user** or **/user/**.
- `/user[/{id}]/` - Same as the previous example, ending trailing slashes are striped before matching.
- `/user/{id:[0-9a-fA-F]{1,8}}` - Only matches if the id parameter consists of 1 to 8 hex digits.
- `/files/{path:.*}` - Matches any URL starting with **/files/** and captures the rest of the path into the parameter **path**.
- `/[{lang:[a-z]{2}}[-{sublang}]/]{name}[/page-{page=0}]` - Matches **/cs/hello**, **/en-us/hello**, **/hello**, **/hello/page-12**, or **/ru/hello/page-23**

Route pattern accepts beginning with a `//domain.com` or `https://domain.com`. Route path also support adding controller (i.e `*<controller@handler>`) directly at the end of the route path:

- `*<App\Controller\BlogController@indexAction>` - translates as a callable of BlogController class with method named indexAction.
- `*<phpinfo>` - translates as a function, if a handler class is defined in route, then it turns to a callable.

Here is an example of how to use the library:

```php
use Flight\Routing\{Router, RouteCollection};

$router = new Router();
$router->setCollection(static function (RouteCollection $routes) {
    $routes->add('/blog/[{slug}]', handler: [BlogController::class, 'indexAction'])->bind('blog_show');
    //... You can add more routes here.
});
```

Incase you'll prefer declaring your routes outside a closure scope, try this example:

```php
use Flight\Routing\{Router, RouteCollection};

$routes = new RouteCollection();
$routes->get('/blog/{slug}*<indexAction>', handler: BlogController::class)->bind('blog_show');

$router = Router::withCollection($routes);
```

> NB: If caching is enabled, using the router's `setCollection()` method has much higher performance than using the `withCollection()` method.

By default Flight routing does not ship a [PSR-7][2] http library nor a library to send response headers and body to the browser. If you'll like to install this libraries, I recommend installing either [biurad/http-galaxy][9] or [nyholm/psr7][10] and [laminas/laminas-httphandlerrunner][11].

```php
$request = ... // A PSR-7 server request initialized from global request

// Routing can match routes with incoming request
$route = $router->matchRequest($request);
// Should return an array, if request is made on a a configured route path (i.e /blog/lorem-ipsum)

// Routing can also generate URLs for a given route
$url = $router->generateUri('blog_show', ['slug' => 'my-blog-post']);
// $url = '/blog/my-blog-post' if stringified else return a GeneratedUri class object
```

In this example below, I'll assume you've installed [nyholm/psr-7][10] and [laminas/laminas-httphandlerrunner][11], So we can use [PSR-15][3] to intercept route before matching and [PSR-17][12] to render route response onto the browser:

```php
use Flight\Routing\Handlers\RouteHandler;
use Laminas\HttpHandlerRunner\Emitter\SapiStreamEmitter;

$router->pipe(...); # Add PSR-15 middlewares ...

$handlerResolver = ... // You can add your own route handler resolver else default is null
$responseFactory = ... // Add PSR-17 response factory
$request = ... // A PSR-7 server request initialized from global request

// Default route handler, a custom request route handler can be used also.
$handler = new RouteHandler($responseFactory, $handlerResolver);

// Match routes with incoming request and return a response
$response = $router->process($request, $handler);

// Send response to the browser ...
(new SapiStreamEmitter())->emit($response);
```

To use [PHP][1] 8 attribute support, I highly recommend installing [biurad/annotations][13] and if for some reason you decide to use [doctrine/annotations][14] I recommend you install [spiral/attributes][15] to use either one or both.

An example using annotations/attribute is:

```php
use Biurad\Annotations\AnnotationLoader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Flight\Routing\Annotation\Listener;
use Spiral\Attributes\{AnnotationReader, AttributeReader};
use Spiral\Attributes\Composite\MergeReader;

$reader = new AttributeReader();

// If you only want to use PHP 8 attribute support, you can skip this step and set reader to null.
if (\class_exists(AnnotationRegistry::class)) $reader = new MergeReader([new AnnotationReader(), $reader]);

$loader = new AnnotationLoader($reader);
$loader->listener(new Listener(), 'my_routes');
$loader->resource('src/Controller', 'src/Bundle/BundleName/Controller');

$annotation = $loader->load('my_routes'); // Returns a Flight\Routing\RouteCollection class instance
```

You can add more listeners to the annotation loader class to have all your annotations/attributes loaded from one place.
Also use either the `populate()` route collection method or `group()` to merge annotation's route collection into default route collection, or just simple use the annotation's route collection as your default router route collection.

Finally, use a restful route, refer to this example below, using `Flight\Routing\RouteCollection::resource`, method means, route becomes available for all standard request methods `Flight\Routing\Router::HTTP_METHODS_STANDARD`:

```php
namespace Demo\Controller;

class UserController {
    public function getUser(int $id): string {
        return "get {$id}";
    }

    public function postUser(int $id): string {
        return "post {$id}";
    }

    public function deleteUser(int $id): string {
        return "delete {$id}";
    }
}
```

Add route using `Flight\Routing\Handlers\ResourceHandler`:

```php
use Flight\Routing\Handlers\ResourceHandler;

$routes->add('/user/{id:\d+}', ['GET', 'POST'], new ResourceHandler(Demo\UserController::class, 'user'));
```

As of Version 2.0, flight routing is very much stable and can be used in production, Feel free to contribute to the project, report bugs, request features and so on.

> Kindly take note of these before using:
> * Avoid declaring the same pattern of dynamic route multiple times (eg. `/hello/{name}`), instead use static paths if you choose use same route path with multiple configurations.
> * Route handlers prefixed with a `\` (eg. `\HelloClass` or `['\HelloClass', 'handle']`) should be avoided if you choose to use a different resolver other the default handler's RouteInvoker class.
> * If you decide again to use a custom route's handler resolver, I recommend you include the static `resolveRoute` method from the default's route's RouteInvoker class.

## 📓 Documentation

In-depth documentation on how to use this library, kindly check out the [documentation][16] for this library. It is also recommended to browse through unit tests in the [tests](./tests/) directory.

## 🙌 Sponsors

If this library made it into your project, or you interested in supporting us, please consider [donating][17] to support future development.

## 👥 Credits & Acknowledgements

- [Divine Niiquaye Ibok][18] is the author this library.
- [All Contributors][19] who contributed to this project.

## 📄 License

Flight Routing is completely free and released under the [BSD 3 License](LICENSE).

[1]: https://php.net
[2]: http://www.php-fig.org/psr/psr-7/
[3]: http://www.php-fig.org/psr/psr-15/
[4]: https://github.com/sunrise-php/http-router
[5]: https://github.com/symfony/routing
[6]: https://github.com/nikic/FastRoute
[7]: https://getcomposer.org
[8]: https://divinenii.com/blog/php-web_server_configuration
[9]: https://github.com/biurad/php-http-galaxy
[10]: https://github.com/nyholm/psr7
[11]: https://github.com/laminas/laminas-httphandlerrunner
[12]: https://www.php-fig.org/psr/psr-17/
[13]: https://github.com/biurad/php-annotations
[14]: https://github.com/doctrine/annotations
[15]: https://github.com/spiral/attributes
[16]: https://divinenii.com/courses/php-flight-routing/
[17]: https://divinenii.com/sponser
[18]: https://github.com/divineniiquaye
[19]: https://github.com/divineniiquaye/flight-routing/contributors
