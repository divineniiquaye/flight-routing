# Flight routing is a simple, fast PHP router that is easy to get integrated with other routers. Partially inspired by the Laravel router

The goal of this project is to create a router that is more or less 100% compatible with all php http routers, while remaining as simple as possible, and as easy to integrate and change without compromising either speed or complexity. Being lightweight is the #1 priority.

First of all, you need to configure your web server to handle all the HTTP requests with a single PHP file like `index.php`. Here you can see required configurations for Apache HTTP Server and NGINX.

**`Please note that this documentation is currently work-in-progress. Feel free to contribute.`**

## Features

- Basic routing (`GET`, `POST`, `PUT`, `PATCH`, `UPDATE`, `DELETE`) with support for custom multiple verbs.
- Regular Expression Constraints for parameters.
- Named routes.
- Generating url to routes.
- Route groups.
- Middleware (classes that intercepts before the route is rendered).
- Namespaces.
- Route prefixes.
- Optional parameters
- Sub-domain routing and more.

## Installation

The recommended way to install Url Routing is via Composer:

```bash
composer require divineniiquaye/flight-routing
```

It requires PHP version 7.0 and supports PHP up to 7.4. The dev-master version requires PHP 7.1.

## How To Use

### Setting up Nginx

If you are using Nginx please make sure that url-rewriting is enabled.

You can easily enable url-rewriting by adding the following configuration for the Nginx configuration-file for the demo-project.

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

### Setting up Apache

Nothing special is required for Apache to work. We've include the `.htaccess` file in the `public` folder. If rewriting is not working for you, please check that the `mod_rewrite` module (htaccess support) is enabled in the Apache configuration.

#### .htaccess example

Below is an example of an working `.htaccess` file used by simple-php-router.

Simply create a new `.htaccess` file in your projects `public` directory and paste the contents below in your newly created file. This will redirect all requests to your `index.php` file (see Configuration section below).

```htaccess
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews
    </IfModule>

    RewriteEngine On

    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)/$ /$1 [L,R=301]

    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

### Setting up IIS

On IIS you have to add some lines your `web.config` file in the `public` folder or create a new one. If rewriting is not working for you, please check that your IIS version have included the `url rewrite` module or download and install them from Microsoft web site.

#### web.config example

Below is an example of an working `web.config` file used by simple-php-router.

Simply create a new `web.config` file in your projects `public` directory and paste the contents below in your newly created file. This will redirect all requests to your `index.php` file (see Configuration section below). If the `web.config` file already exists, add the `<rewrite>` section inside the `<system.webServer>` branch.

```xml
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <system.webServer>
        <defaultDocument>
            <files>
                <remove value="index.php" />
                <add value="index.php" />
            </files>
        </defaultDocument>
        <rewrite>
            <!-- Remove slash '/' from the en of the url -->
            <rules>
                <rule name="request_filename" stopProcessing="true">
                    <match url="^.*$" ignoreCase="true" />
                    <!-- When requested file or folder don't exists, will request again through index.php -->
                    <conditions logicalGrouping="MatchAll">
                        <add input="{REQUEST_FILENAME}" matchType="IsFile" ignoreCase="false" negate="true" />
                        <add input="{REQUEST_FILENAME}" matchType="IsDirectory" ignoreCase="false" negate="true" />
                    </conditions>
                    <action type="Rewrite" url="index.php" />
                </rule>
            </rules>
        </rewrite>
    </system.webServer>
    <system.web>
        <httpRuntime requestPathInvalidCharacters="&lt;,&gt;,*,%,&amp;,\,?" />
    </system.web>
</configuration>
```

#### Troubleshooting

If you do not have a `favicon.ico` file in your project, you can get a `NotFoundHttpException` (404 - not found).
To add `favicon.ico` to the IIS ignore-list, add the following line to the `<conditions>` group:

```xml
<add input="{REQUEST_FILENAME}" negate="true" pattern="favicon.ico" ignoreCase="true" />
```

You can also make one exception for files with some extensions:

```xml
<add input="{REQUEST_FILENAME}" pattern="\.ico|\.png|\.css|\.jpg" negate="true" ignoreCase="true" />
```

If you are using `$_SERVER['ORIG_PATH_INFO']`, you will get `\index.php\` as part of the returned value. For example:

```text
/index.php/test/mypage.php
```

### Configuration

Create a new file, name it `routes.php` and place it in your library folder or any private folder. This will be the file where you define all the routes for your project.

**WARNING: NEVER PLACE YOUR ROUTES.PHP IN YOUR PUBLIC FOLDER!**

In your ```index.php``` require your newly-created ```routes.php``` and call the ```$router->dispatch()``` method. This will trigger and do the actual routing of the requests.

It's not required, but you can set `namespace method paramter's value to '\Demo\Controllers';` to prefix all routes with the namespace to your controllers. This will simplify things a bit, as you won't have to specify the namespace for your controllers on each route.

Router uses [biurad-http](https://github.com/biurad/biurad-hhtp) package to provide [PSR-7](https://www.php-fig.org/psr/psr-7) complaint request and response objects to your controllers and middleware.

run this in command line if the package has not be added.

```bash
composer require biurad/biurad-http
```

Please note that this example only covers how to this router in a project without an existing framework. If you are using a framework in your project, the implementation varies.

**This is an example of how your ```routes.php``` file shoud start:**

```php
use BiuradPHP\Http\Factory\GuzzleHttpPsr7Factory as Psr17Factory;
use BiuradPHP\Routing\RouteCollector as Router;

// Need th have extensive idea in php before using this dependency ,though it easy to use.
$router = new Router(Psr17Factory::fromGlobalRequest(), new Psr17Factory);

/**
 * The default namespace for route-callbacks, so we don't have to specify it each time.
 * Can be overwritten by using the namespace config option on your routes.
 */
$router->setNamespace('\Demo\Controllers');

// Routes goes here...

// All routers goes in this space of the file
return $router;
```
There are two ways of dipatching a router, either by using the default Flight\Routing\Services\HttpPublisher or BiuradPHP\Http\Response\EmitResponse to dispatch the router.

**This is an example of a basic ```index.php``` file:**

```php
<?php

use Flight\Routing\Services\HttpPublisher;
use BiuradPHP\Http\Response\EmitResponse;

/* Load external routes file */
$router = require_once 'routes.php';
assert($router instanceof BiuradPHP\Routing\RouteCollector);

// Start the routing
return (new EmitResponse)->emit($router->dispatch());
// or 
return (new HttpPublisher)->publish($router->dispatch(), new EmitResponse);
```

Remember the ```routes.php``` file you required in your ```index.php```? This file be where you place all your custom rules for routing.

> **NOTE**: If your handler return type isn't instance of ResponseInterface, FLight Routing will choose the best content-type for http response. Returning strings can be abit of conflict for Flight routing, so it fallback is "text/html", a plain text where isn't xml, doesn't contain a <!doctype> or doesn't have a <html>...</html> wrapped around contents will return a content-type of text/plain.

## Basic routing

Below is a very basic example of setting up a route. First parameter is the url which the route should match - next parameter is a `Closure` or callback function that will be triggered once the route matches.

```php
$router->get('/', function() {
  return 'Hello world';
}); // $router from routes.php file.
```

### Request

You can catch the request object like this example:

```php
use Zend\Diactoros\ServerRequest;
use BiuradPHP\Http\Response\EmptyResponse;
use BiuradPHP\Http\Response\JsonResponse;

$router->get('/', function (ServerRequest $request) {
    return new JsonResponse([
        'method' => $request->getMethod(),
        'uri' => $request->getUri(),
        'body' => $request->getBody(),
        'parsedBody' => $request->getParsedBody(),
        'headers' => $request->getHeaders(),
        'queryParameters' => $request->getQueryParams(),
        'attributes' => $request->getAttributes(),
    ]);
});

$router->post('/blog/posts', function (ServerRequest $request) {
    $post = new \Demo\Models\Post();
    $post->title = $request->getQueryParams()['title'];
    $post->content = $request->getQueryParams()['content'];
    $post->save();

    return new EmptyResponse(201);
});
```

### Response

The example below illustrates supported kinds of responses.

```php
use BiuradPHP\Http\Response\EmptyResponse;
use BiuradPHP\Http\Response\HtmlResponse;
use BiuradPHP\Http\Response\JsonResponse;
use BiuradPHP\Http\Response\TextResponse;

$router
    ->get('/html/1', function () {
        return '<html>This is an HTML response</html>';
    });
    ->get('/html/2', function () {
        return new HtmlResponse('<html>This is also an HTML response</html>', 200);
    });
    ->get('/json', function () {
        return new JsonResponse(['message' => 'Unauthorized!'], 401);
    });
    ->get('/text', function () {
        return new TextResponse('This is a plain text...');
    });
    ->get('/empty', function () {
        return new EmptyResponse();
    });

```

#### Redirection Response

In case of needing to redirecting user to another URL:

```php
use BiuradPHP\Http\Response\RedirectResponse;

$router
    ->get('/redirect', function () {
        return new RedirectResponse('https://biurad.com');
    });
```

### Available methods

Here you can see how to declare different routes with different http methods:

```php
$router
    ->get('/', function () {
        return '<b>GET method</b>';
    });
    ->post('/', function () {
        return '<b>POST method</b>';
    });
    ->patch('/', function () {
        return '<b>PATCH method</b>';
    });
    ->put('/', function () {
        return '<b>PUT method</b>';
    });
    ->delete('/', function () {
        return '<b>DELETE method</b>';
    });
```

### Multiple HTTP-verbs

Sometimes you might need to create a route that accepts multiple HTTP-verbs. If you need to match all HTTP-verbs you can use the `any` method.

```php
$router->map(['get', 'post'], '/', function() {
  // ...
});

$router->any('foo', function() {
  // ...
});
```

## Route parameters

### Required parameters

You'll properly wondering by know how you parse parameters from your urls. For example, you might want to capture the users id from an url. You can do so by defining route-parameters.

```php
$router->get('/user/{userId}', function ($userId) {
  return 'User with id: ' . $userId;
});
```

You may define as many route parameters as required by your route:

```php
$router->get('/posts/{postId}/comments/{commentId}', function ($postId, $commentId) {
  // ...
});
```

### Optional parameters

Occasionally you may need to specify a route parameter, but make the presence of that route parameter optional. You may do so by placing a ? mark after the parameter name. Make sure to give the route's corresponding variable a default value:

```php
// Optional parameter
$router->get('/user/{name?}', function ($name = null) {
  return $name;
});

// Optional parameter with default value
$router->get('/user/{name?}', function ($name = 'Simon') {
  return $name;
});
```

**Note:** Route parameters are always encased within {} braces and should consist of alphabetic characters. Route parameters may not contain a - character. Use an underscore (_) instead.

### Regular expression constraints

You may constrain the format of your route parameters using the where method on a route instance. The where method accepts the name of the parameter and a regular expression defining how the parameter should be constrained:

```php
$router->get('/user/{name}', function ($name) {
    //
})->setPattern('name', '[A-Za-z]+');

$router->get('/user/{id}', function (int $id) {
    //
})->setPattern('id', '[0-9]+');

$router->get('/user/{id}/{name}', function (int $id, string $name) {
    //
})->whereArray(['id' => '[0-9]+', 'name' => '[a-z]+']);

$router->get('/user/{id<[0-9]+>}/{name<[a-z]+>}', function (int $id, string $name) {
    //
});
```

## Named routes

Named routes allow the convenient generation of URLs or redirects for specific routes. You may specify a name for a route by chaining the name method onto the route definition:

```php
$router->get('/user/profile', function () {
    // Your code here
})->setName('profile');
```

You can also specify names for Controller-actions:

```php
$router->get('/user/profile', 'UserController@profile')->setName('profile');
```

### Generating URLs To Named Routes

Once you have assigned a name to a given route, you may use the route's name when generating URLs or redirects via the global `url` helper-function (see helpers section):

```php
// Generating URLs...
$url = $router->generateUri('profile');
```

If the named route defines parameters, you may pass the parameters as the second argument to the `url` function. The given parameters will automatically be inserted into the URL in their correct positions:

```php
$router->get('/user/{id}/profile', function ($id) {
    //
})->setName('profile');

$url = $router->generateUri('profile', ['id' => 1]);
```

## Router groups

Route groups allow you to share route attributes, such as middleware or namespaces, across a large number of routes without needing to define those attributes on each individual route. Shared attributes are specified in an array format as the first parameter to the `$router->group` method.

### Middleware

Router supports middleware, you can use it for different purposes like authentication, authorization, throttles and so forth. Middleware run before controllers and it can check and manipulate http requests.

Here you can see the request lifecycle considering some middleware:

```text
Input --[Request]↦ Router ↦ Middleware 1 ↦ ... ↦ Middleware N ↦ Controller
                                                                      ↧
Output ↤[Response]- Router ↤ Middleware 1 ↤ ... ↤ Middleware N ↤ [Response]
```

We using using [laminas-stratigility](https://github.com/laminas/laminas-stratigility) to allow better and saver middleware usage. install [laminas-stratigility](https://github.com/zendframework/laminas-stratigility) via composer:

```bash
composer require laminas/laminas-stratigility
```

To declare a middleware, you must implements Middleware `Psr\Http\Server\MiddlewareInterface` interface.

Middleware must have a `process()` method that catches http request and a closure (which runs the next middleware or the controller) and it returns a response at the end. Middleware can break the lifecycle and return a response itself or it can run the `$handler` implementing `Psr\Http\Server\RequestHandlerInterface` to continue lifecycle.

For example see the following snippet. In this snippet, if there was a `Authorization` header in the request,
it passes the request to the next middleware or the controller (if there is no more middleware left) and if the header is absent it returns an empty response with `401 Authorization Failed` HTTP status code.

```php
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use BiuradPHP\Http\Response\EmptyResponse;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class AuthMiddleware implements Middleware
{
    public function process(Request $request, RequestHandler $handler): ResponseInterface
    {
        if ($request->getHeader('Authorization')) {
            return $handler->handle($request);
        }

        return new EmptyResponse(401);
    }
}
```

To assign middleware to all routes within a group, you may use the middleware key in the group attribute array. Middleware are executed in the order they are listed in the array:

```php
$router->group(['middleware' => \Demo\Middleware\AuthMiddleware::class], function (RouterProxyInterface $route) {
    $route->get('/', function ()    {
        // Uses Auth Middleware
    });

    $route->get('/user/profile', function () {
        // Uses Auth Middleware
    });
});

// or

$router->get('/auth', function () {
   // Uses Auth Middleware
})->addMiddleware(\Demo\Middleware\AuthMiddleware::class);

```

Middleware can be implemented using closures but it doesn’t make scense to do so!

### Namespaces

Another common use-case for route groups is assigning the same PHP namespace to a group of controllers using the `namespace` parameter in the group array:

#### Note

Group namespaces will only be added to routes with relative callbacks.
For example if your route has an absolute callback like `\Demo\Controller\DefaultController@home`, the namespace from the route will not be prepended.
To fix this you can make the callback relative by removing the `\` in the beginning of the callback.

```php
$router->group(['namespace' => 'Admin'], function (RouterProxyInterface $route) {
    // Controllers Within The "App\Http\Controllers\Admin" Namespace
});
```

### Subdomain-routing

Route groups may also be used to handle sub-domain routing. The sub-domain may be specified using the `domain` key on the group attribute array:

```php
// Domain
$router->get('/', 'Controller@method')->setDomain('domain.com');

// Subdomain
$router->get('/', 'Controller:method')->setDomain('server2.domain.com');

// Subdomain regex pattern
$router->get('/', ['Controller', 'method'])->setDomain('{accounts<.*>}.domain.com');

$router->group(['domain' => 'account.myapp.com'], function (RouterProxyInterface $route) {
    $route->get('/user/{id}', function ($id) {
        //
    });
});
```

### Route prefixes

The `prefix` group attribute may be used to prefix each route in the group with a given url. For example, you may want to prefix all route urls within the group with `admin`:

```php
$router->group(['prefix' => '/admin'], function ($route) {
    $route->get('/users', function ()    {
        // Matches The "/admin/users" URL
    });
});
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Testing

To run the tests you'll have to start the included node based server first if any in a separate terminal window.

With the server running, you can start testing.

```bash
vendor/bin/phpunit
```

## Security

If you discover any security related issues, please report using the issue tracker.
use our example [Issue Report](.github/ISSUE_TEMPLATE/Bug_report.md) template.

## Want to be listed on our projects website

You're free to use this package, but if it makes it to your production environment we highly appreciate you sending us a message on our website, mentioning which of our package(s) you are using.

Post Here: [Project Patreons - https://patreons.biurad.com](https://patreons.biurad.com)

We publish all received request's on our website;

## Credits

- [Divine Niiquaye](https://github.com/divineniiquaye)
- [All Contributors](https://biurad.com/projects/flight-routing/contributers)

## Support us

I am Niquaye Divine a software engineer at [`Biurad Lap`](https://biurad.com), Ghana. You'll find an overview of all our open source projects [on our website](https://biurad.com/opensource).

Does your business depend on our contributions? Reach out and support us on to build more project's. We want to build over one hundred project's in two years. [Support Us](https://biurad.com/donate) achieve our goal.

All pledges will be dedicated to allocating workforce on maintenance and new awesome stuff.
[Thanks to all who made Donations and Pledges to Us.](.github/ISSUE_TEMPLATE/Support_us.md)

## License

The BSD-3-Clause . Please see [License File](LICENSE.md) for more information.
