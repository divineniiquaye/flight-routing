# Flight routing is a simple, fast PHP router that is easy to get integrated with other routers. Partially inspired by the Laravel router

[![FOSSA Status](https://app.fossa.io/api/projects/git%2Bgithub.com%2Fdivineniiquaye%2Fflight-routing.svg?type=shield)](https://app.fossa.io/projects/git%2Bgithub.com%2Fdivineniiquaye%2Fflight-routing?ref=badge_shield)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/divineniiquaye/flight-routing/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/divineniiquaye/flight-routing/?branch=master)

The goal of this project is to create a router that is more or less 100% compatible with all php http routers, while remaining as simple as possible, and as easy to integrate and change without compromising either speed or complexity. Being lightweight is the #1 priority.

Human-friendly URLs (also more cool & prettier) are easier to remember and do help SEO (search engine optimalization). Flight Routing allows you to use any current trends of *router* implementation and fully meets developers' desires.

The desired URL format is set by a *router*. The plainest implementation of the router is [DefaultFlightRouter](https://github.com/divineniiquaye/flight-routing/blob/master/src/Services/DefaultFlightRouter.php). It can be used when there's no *router* for a specific URL format yet.

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

It requires PHP version 7.2 and supports PHP up to 7.4. The dev-master version requires PHP 7.3.

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
___

Below is an example of an working `.htaccess` file used by flight-routing.

Simply create a new `.htaccess` file in your projects `public` directory and paste the contents below in your newly created file. This will redirect all requests to your `index.php` file (see Configuration section below).

```htaccess
<IfModule mod_rewrite.c>
    Options -MultiViews
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^(.*)$ index.php [QSA,L]
</IfModule>

<IfModule !mod_rewrite.c>
    <IfModule mod_alias.c>
        RedirectMatch 307 ^/$ /index.php/
    </IfModule>
</IfModule>

```

### Setting up IIS

On IIS you have to add some lines your `web.config` file in the `public` folder or create a new one. If rewriting is not working for you, please check that your IIS version have included the `url rewrite` module or download and install them from Microsoft web site.

#### web.config example
___

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
___

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

## Configuration

Please note that the following snippets only covers how to this router in a project without an existing framework using [DefaultFlightRouter](https://github.com/divineniiquaye/flight-routing/blob/master/src/Services/DefaultFlightRouter.php) *router*. If you are using a framework or/and a different *router* in your project, the implementation varies.

It's not required, but you can set `namespace method paramter's value to eg: '\Demo\Controllers';` to prefix all routes with the namespace to your controllers. This will simplify things a bit, as you won't have to specify the namespace for your controllers on each route.

Router uses [biurad-http](https://github.com/biurad/biurad-http) package to provide [PSR-7](https://www.php-fig.org/psr/psr-7) complaint request and response objects to your controllers and middleware.

run this in command line if the package has not be added.

```bash
composer require biurad/biurad-http
```

Flight routing allows you to call any controller action with namespace using `*<namepace\controller@action>` pattern, also you have have domain on route pattern using `//` followed by the host and path, or add a scheme to the pattern.

> Create a new file, name it `routes.php` and place it in your library folder or any private folder. This will be the file where you define all the routes for your project.

In your ```index.php``` require your newly-created ```routes.php``` and call the ```$router->handle()``` method on [HttpPublisher](https://github.com/divineniiquaye/flight-routing/blob/master/src/Services/HttpPublisher.php) *publish* method, passing an instance of [PSR-7](https://www.php-fig.org/psr/psr-7) *ServerRequestInterface*. This will trigger and do the actual routing of the requests to response.

```php
<?php

use BiuradPHP\Http\Factory\GuzzleHttpPsr7Factory as Psr17Factory;
use Flight\Routing\RouteCollector as Router;

// Need to have an idea about php before using this dependency, though it easy to use.
$router = new Router(new Psr17Factory);

/**
 * The default namespace for route-callbacks, so we don't have to specify it each time.
 * Can be overwritten by using the namespace config option on your routes.
 */
$router->setNamespace('\Demo\Controllers');

// Routes goes here...

// All routers goes in this space of the file
return $router;
```
There are two ways of dipatching a router, either by using the default [HttpPublisher](https://github.com/divineniiquaye/flight-routing/blob/master/src/Services/HttpPublisher.php) or [EmitResponse](https://github.com/biurad/biurad-http/blob/master/src/Response/EmitResponse.php) to dispatch the router.

**This is an example of a basic ```index.php``` file:**

```php
<?php

use Flight\Routing\Services\HttpPublisher;
use BiuradPHP\Http\Factory\GuzzleHttpPsr7Factory as Psr17Factory;

/* Load external routes file */
$router = require_once __DIR__.'/routes.php';
assert($router instanceof BiuradPHP\Routing\RouteCollector);

// Start the routing
return (new HttpPublisher)->publish($router->handle(Psr17Factory::fromGlobalRequest()));

// or use
```

Remember the ```routes.php``` file you required in your ```index.php```? This file be where you place all your custom rules for routing.

> **NOTE**: If your handler return type isn't instance of ResponseInterface, FLight Routing will choose the best content-type for http response. Returning strings can be abit of conflict for Flight routing, so it fallback is "text/html", a plain text where isn't xml, doesn't contain a <!doctype> or doesn't have a <html>...</html> wrapped around contents will return a content-type of text/plain.

> The Route class can accept a handler of type `Psr\Http\Server\RequestHandlerInterface`, callabe, invokable class,
> or array of [class, method]. Simply pass a class or a binding name instead of a real object if you want it
> to be constructed on demand.

## Basic routing

Below is a very basic example of setting up a route. First parameter is the url which the route should match - next parameter is a `Closure` or callback function that will be triggered once the route matches.

```php
$router->get('/', function() {
  return 'Hello world';
}); // $router from routes.php file.
```

## Closure Handler

It is possible to pass the `closure` as route handler, in this case our function will receive two
arguments: `Psr\Http\Message\ServerRequestInterface` and `Psr\Http\Message\ResponseInterface`.

```php
$router->get(
    '/{name}',
    function (ServerRequestInterface $request, ResponseInterface $response) {
        $response->getBody()->write("hello world");

        return $response;
    }
));
```

## Route Request

You can catch the request object like this example:

```php
<?php

use BiuradPHP\Http\ServerRequest;
use BiuradPHP\Http\Response\EmptyResponse;
use BiuradPHP\Http\Response\JsonResponse;

$router->get(
    '/',
    function (ServerRequest $request) {
        return new JsonResponse([
            'method'            => $request->getMethod(),
            'uri'               => $request->getUri(),
            'body'              => $request->getBody(),
            'parsedBody'        => $request->getParsedBody(),
            'headers'           => $request->getHeaders(),
            'queryParameters'   => $request->getQueryParams(),
            'attributes'        => $request->getAttributes(),
        ]);
    }
);

$router->post(
    '/blog/posts',
    function (ServerRequest $request) {
        $post           = new \Demo\Models\Post();
        $post->title    = $request->getQueryParams()['title'];
        $post->content  = $request->getQueryParams()['content'];
        $post->save();

        return new EmptyResponse(201);
    }
);
```

## Route Response

The example below illustrates supported kinds of responses.

```php
<?php

use BiuradPHP\Http\Response\EmptyResponse;
use BiuradPHP\Http\Response\HtmlResponse;
use BiuradPHP\Http\Response\JsonResponse;
use BiuradPHP\Http\Response\TextResponse;

$router
    ->get(
        '/html/1',
        function () {
            return '<html>This is an HTML response</html>';
        }
    );
$router
    ->get(
        '/html/2',
        function () {
            return new HtmlResponse('<html>This is also an HTML response</html>', 200);
        }
    );
$router
    ->get(
        '/json',
        function () {
            return new JsonResponse(['message' => 'Unauthorized!'], 401);
        }
    );
$router
    ->get(
        '/text',
        function () {
            return new TextResponse('This is a plain text...');
        }
    );
$router
    ->get(
        '/empty',
        function () {
            return new EmptyResponse();
        }
    );

```

## Route Redirection Response

In case of needing to redirecting user to another URL:

```php
<?php

use BiuradPHP\Http\Response\RedirectResponse;

$router
    ->get('/redirect', function () {
        return new RedirectResponse('https://biurad.com');
    });
```

## Available Methods

Here you can see how to declare different routes with different http methods:

```php
$router
    ->get('/', function () {
        return '<b>GET method</b>';
    });
$router
    ->post('/', function () {
        return '<b>POST method</b>';
    });
$router
    ->patch('/', function () {
        return '<b>PATCH method</b>';
    });
$router
    ->put('/', function () {
        return '<b>PUT method</b>';
    });
$router
    ->delete('/', function () {
        return '<b>DELETE method</b>';
    });
```

## Multiple HTTP-Verbs

Sometimes you might need to create a route that accepts multiple HTTP-verbs. If you need to match all HTTP-verbs you can use the `any` method.

```php
$router->map(['get', 'post'], '/', function() {
  // ...
});

$router->any('foo', function() {
  // ...
});
```

## Route Pattern and Parameters

You can use route pattern to specify any number of required and optional parameters, these parameters will later be passed
to our route handler via `ServerRequestInterface` attribute `route`.

Use the `{parameter_name:pattern}` form to define a route parameter, where pattern is a regexp friendly expression. You can
omit pattern and just use `{parameter_name}`, in this case the parameter will match `[^\/]+`.

### Required Parameters

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

### Optional Parameters

Occasionally you may need to specify a route parameter, but make the presence of that route parameter optional. Use `[]` to make a part of route (including the parameters) optional, for example:

```php
// Optional parameter
$router->get('/user[/{name}]', function ($name = null) {
  return $name;
});
//or
$router->get('/user[/{name}]', function ($name) {
  return $name;
});
```

```php
// Optional parameter with default value
$router->get('/user/[{name}]', function ($name = 'Simon') {
  return $name;
});
//or
$router->get('/user/[{name=<Simon>}]', function ($name) {
  return $name;
});
```

Obviously, if a parameter is inside an optional sequence, it's optional too and defaults to `null`. Sequence should define it's surroundings, in this case a slash which must follow a parameter, if set. The technique may be used for example for optional language subdomains:

```php
$router->get('//[{lang=<en>}.]example.com/hello', ...);
```

Sequences may be freely nested and combined:

```php
$router->get('[{lang:[a-z]{2}}[-{sublang}]/]{name}[/page-{page=<0>}]', ...);

// Accepted URLs:
// /cs/hello
// /en-us/hello
// /hello
// /hello/page-12
```

**Note:** Route parameters are always encased within {} braces and should consist of alphabetic characters. Route parameters may not contain a - character. Use an underscore (_) instead.

### Regular Expression Constraints

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

$router->get('/user/{id:[0-9]+}/{name:[a-z]+}', function (int $id, string $name) {
    //
});
```

## Named Routes

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

## Generating URLs From Named Routes

URL generator tries to keep the URL as short as possible (while unique), so what can be omitted is not used. The behavior of generating urls from route depends on the respective parameters sequence given.

Once you have assigned a name to a given route, you may use the route's name, its parameters and maybe add query, when generating URLs:

```php
// Generating URLs...
$url = $router->generateUri('profile');
```

If the named route defines parameters, you may pass the parameters as the second argument to the `url` function. The given parameters will automatically be inserted into the URL in their correct positions:

```php
$router->get('/user/{id}/profile', function ($id) {
    //
})->setName('profile');

$url = $router->generateUri('profile', ['id' => 1]); // will produce "user/1/profile"
```

## Route Groups

Route groups allow you to share route attributes, such as middlewares, namespace, domain, name, prefix, patterns, or defaults, across a large number of routes without needing to define those attributes on each individual route. Shared attributes are specified in an array format as the first parameter to the `$router->group` method.

```php
<?php

use Flight\Routing\Interfaces\RouterProxyInterface;

$router->group(
    [...], // Add your group attributes
    function (RouterProxyInterface $route) {
        // Define your routes using $route...
    }
);
```

## Route Middlewares

Router supports middleware, you can use it for different purposes like authentication, authorization, throttles and so forth. Middleware run before controllers and it can check and manipulate http requests. To associate route specific middleware use `addMiddleware`, you can access route parameters via `arguments` attribute of the request object:

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

For example see the following snippet. In this snippet, we will demonstrate how a mddlewares works:

```php
<?php

use Demo\Middleware\ParamWatcher;

$router->get(
    '/{param}',
    function (ServerRequestInterface $request, ResponseInterface $response) {
        return $request->getAttribute('arguments');
    }
))
->addMiddleware(ParamWatcher::class);
```

where `ParamWatcher` is:

```php
<?php

namespace Demo\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use BiuradPHP\Http\Exceptions\ClientException\UnauthorizedException;

class ParamWatcher implements MiddlewareInterface
{
    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        if ($request->getAttribute('arguments')['param'] === 'forbidden') {
           throw new UnauthorizedException();
        }

        return $handler->handle($request);
    }
}
```

This route will trigger Unauthorized exception on `/forbidden`.

> You can add as many middlewares as you want. Middlewares can be implemented using closures but it doesn’t make scense to do so!

## Multiple Routes

Flight Routing increases SEO (search engine optimization) as it prevents multiple URLs to link to different content (without a proper redirect). If more than one addresses link to the same target, the router choices the first (makes it canonical), while the other routes are never reached. Thanks to that your page won't have duplicities on search engines and their rank won't be split.

This whole process is called *canonicalization*. Default (canonical) URL is the one router generates, that is, the first route matches exactly. Router will match all routes in the order they were registered. Make sure to avoid situations where previous route matches the conditions of the following routes.

```php
$router->get(
    '/{param}',
    function (ServerRequestInterface $request, ResponseInterface $response) {
        return $request->getAttribute('arguments');
    }
))

// this route will never trigger
$router->get(
    '/hello',
    function (ServerRequestInterface $request, ResponseInterface $response) {
        return $request->getAttribute('arguments');
    }
))
```

## Subdomain Routing

Route groups may also be used to handle sub-domain routing. The sub-domain may be specified using the `domain` key on the group attribute array:

```php
// Domain
$router->get('/', 'Controller@method')->setDomain('domain.com');

// Subdomain
$router->get('/', 'Controller:method')->setDomain('server2.domain.com');

// Subdomain regex pattern
$router->get('/', ['Controller', 'method'])->setDomain('{accounts:.*}.domain.com');

$router->group(['domain' => 'account.myapp.com'], function (RouterProxyInterface $route) {
    $route->get('/user/{id}', function ($id) {
        //
    });
});
```

## Custom Router

If these offered routes do not fit your needs, you may create your own router and add it to your *router collection*. Router is nothing more than an implementation of [RouterInterface](https://github.com/divineniiquaye/flight-routing/blob/master/src/Interfaces/RouterInterface.php) with its six methods:

```php
<?php

use ArrayIterator;
use Psr\Http\Message\ServerRequestInterface;
use Flight\Routing\Interfaces\RouterInterface;
use Flight\Routing\Interfaces\RouteInterface;
use Flight\Routing\RouteResults;

class MyRouter implements RouterInterface
{
    /** @var array */
    private $routes = [];

    /**
     * {@inheritdoc}
     */
    public function addRoute(RouteInterface $route) : void
    {
        $this->routes[] = $route;
    }

	/**
     * {@inheritdoc}
     */
    public function match(ServerRequestInterface $request): RouteResults
	{
		// ...
	}

    /**
     * {@inheritdoc}
     */
	public function generateUri(RouteInterface $route, array $substitutions = []): string
	{
		// ...
    }

    /**
     * {@inheritdoc}
     */
    public function __clone()
    {
        foreach ($this->routes as $name => $route) {
            $this->routes[$name] = clone $route;
        }
    }

    /**
     * Gets the current RouterInterface as an Iterator that includes all routes.
     *
     * It implements IteratorAggregate.
     *
     * @return ArrayIterator|RouteInterface[] An ArrayIterator object for iterating over routes
     */
    public function getIterator()
    {
        return new ArrayIterator($this->routes);
    }

    /**
     * Gets the number of Routes in this collection.
     *
     * @return int The number of routes
     */
    public function count(): int
    {
        return count($this->routes);
    }
}
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

[![FOSSA Status](https://app.fossa.io/api/projects/git%2Bgithub.com%2Fdivineniiquaye%2Fflight-routing.svg?type=large)](https://app.fossa.io/projects/git%2Bgithub.com%2Fdivineniiquaye%2Fflight-routing?ref=badge_large)
